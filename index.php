<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/contact_helpers.php';
require_once __DIR__ . '/includes/mailer.php';
$db = get_db();
ensure_contact_messages_table($db);
$registrationEnabled = (bool)$db->query('SELECT id FROM administrators LIMIT 1')->fetchColumn();
$contactError = '';
$contactValues = ['full_name' => '', 'email' => '', 'area' => '', 'message' => ''];
if (empty($_SESSION['contact_csrf'])) $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['contact_submit']) || isset($_POST['contact_verify']))) {
    foreach ($contactValues as $key => $_) {
        $value = $_POST[$key] ?? '';
        $contactValues[$key] = is_string($value) ? trim($value) : '';
    }
    $token = (string)($_POST['contact_csrf'] ?? '');
    if (!hash_equals($_SESSION['contact_csrf'], $token)) {
        $contactError = 'Your form session expired. Please refresh the page and try again.';
    } elseif (isset($_POST['contact_verify'])) {
        $pendingId = (int)($_SESSION['contact_pending_id'] ?? 0);
        $pendingToken = (string)($_SESSION['contact_pending_token'] ?? '');
        $code = trim((string)($_POST['verification_code'] ?? ''));
        $stmt = $db->prepare('SELECT * FROM contact_messages WHERE id=? AND verification_token_hash=? AND email_verified=0 LIMIT 1');
        $stmt->execute([$pendingId, hash('sha256', $pendingToken)]);
        $pending = $stmt->fetch();
        if (!$pending || !$pending['verification_expires_at'] || strtotime($pending['verification_expires_at']) < time()) {
            unset($_SESSION['contact_pending_id'], $_SESSION['contact_pending_token']);
            $contactError = 'That verification code expired. Please submit your message again.';
        } elseif ((int)$pending['verification_attempts'] >= 5) {
            unset($_SESSION['contact_pending_id'], $_SESSION['contact_pending_token']);
            $contactError = 'Too many incorrect codes. Please submit your message again.';
        } elseif (password_verify($code, (string)$pending['verification_code_hash'])) {
            $db->prepare('UPDATE contact_messages SET email_verified=1,verified_at=NOW(),verification_code_hash=NULL,verification_token_hash=NULL,verification_expires_at=NULL WHERE id=?')
                ->execute([$pendingId]);
            send_contact_admin_notification((string)$pending['full_name'], (string)$pending['email'], $pending['area'] !== null ? (string)$pending['area'] : null, (string)$pending['message']);
            unset($_SESSION['contact_pending_id'], $_SESSION['contact_pending_token']);
            $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
            header('Location: /disbasura/index.php?contact=verified');
            exit;
        } else {
            $db->prepare('UPDATE contact_messages SET verification_attempts=verification_attempts+1 WHERE id=?')->execute([$pendingId]);
            $contactError = 'That code did not match. Check the email and try again.';
        }
    } else {
        $email = strtolower($contactValues['email']);
        if ($contactValues['full_name'] === '' || strlen($contactValues['full_name']) > 120 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 ||
            !preg_match('/@gmail\.com$/i', $email) || strlen($contactValues['area']) > 160 ||
            $contactValues['message'] === '' || strlen($contactValues['message']) > 3000) {
            $contactError = 'Enter a valid Gmail address, your name, and a message.';
        } else {
            $rate = $db->prepare('SELECT COUNT(*) FROM contact_messages WHERE email=? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            $rate->execute([$email]);
            if ((int)$rate->fetchColumn() >= 3) {
                $contactError = 'Too many verification attempts for this Gmail address. Please try again later.';
            } else {
                $code = (string)random_int(100000, 999999);
                $sessionToken = bin2hex(random_bytes(32));
                $stmt = $db->prepare('INSERT INTO contact_messages (full_name,email,area,message,verification_code_hash,verification_token_hash,verification_expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                $stmt->execute([$contactValues['full_name'], $email, $contactValues['area'] ?: null, $contactValues['message'], password_hash($code, PASSWORD_DEFAULT), hash('sha256', $sessionToken)]);
                $messageId = (int)$db->lastInsertId();
                if (!send_contact_verification_email($email, $contactValues['full_name'], $code)) {
                    $db->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$messageId]);
                    $contactError = 'We could not send a verification email. The Gmail sender must be configured by the system administrator.';
                } else {
                    $_SESSION['contact_pending_id'] = $messageId;
                    $_SESSION['contact_pending_token'] = $sessionToken;
                    header('Location: /disbasura/index.php?contact=verify');
                    exit;
                }
            }
        }
    }
}
$contactPending = !empty($_SESSION['contact_pending_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#001912">
  <link rel="icon" type="image/png" href="/disbasura/assets/images/disbasura-icon.png">
  <title>DisBasura</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--green:#00a879;--green-dark:#003d2f;--mint:#6ce8b5;--ink:#f7fffb;--muted:#a7c8bc;--line:#123e34;--pale:#062e25}
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth;scroll-padding-top:82px}
    body{font-family:'Plus Jakarta Sans',sans-serif;color:#18382d;background:#f4faf6;-webkit-font-smoothing:antialiased}
    a{color:inherit;text-decoration:none}
    .container{width:min(1220px,calc(100% - 60px));margin:auto}
    .site-header{height:92px;background:rgba(255,255,255,.94);border-bottom:1px solid #e4efe8;position:sticky;top:0;z-index:10;backdrop-filter:blur(14px);box-shadow:0 8px 24px rgba(18,62,43,.045)}
    .nav{height:100%;display:flex;align-items:center;justify-content:space-between;gap:20px}
    .brand{display:flex;align-items:center;gap:10px;min-width:250px;padding:7px 8px;border:1px solid transparent;border-radius:18px;background:transparent}
    .brand-mark{width:56px;height:56px;display:block;flex:none;object-fit:contain;border-radius:0;background:transparent;box-shadow:none}
    .brand-name{font-size:21px;line-height:1.15;font-weight:800;letter-spacing:-.8px;color:#143b30}
    .brand-tag{display:block;color:#16865f;font-size:10px;font-weight:800;letter-spacing:.35px;text-transform:uppercase;margin-top:2px}
    .nav-links{display:flex;align-items:center;gap:6px;padding:5px;border:1px solid transparent;border-radius:17px;background:transparent;color:#435c50;font-weight:800;font-size:13px}
    .nav-links a,.nav-actions a{transition:color .18s,background .18s,border-color .18s,transform .18s}
    .nav-links a{padding:10px 19px;border:1px solid transparent;border-radius:14px;background:transparent}
    .nav-links a:hover,.nav-links a:focus-visible,.nav-links a.active{color:#087a55;border-color:#d8f1e3;background:#effaf3}
    .nav-actions{display:flex;align-items:center;gap:12px;font-size:13px;font-weight:800;color:#315044}
    .login-link{padding:10px 20px;border:1px solid #d3e7da;border-radius:999px;background:#fff}
    .login-link:hover{color:#087a55;background:#effaf3}
    .signup{padding:10px 23px;border:1px solid #0eaa73;border-radius:999px;background:linear-gradient(110deg,#19bd82,#079d6d);color:white;box-shadow:0 7px 18px rgba(0,168,121,.2)}
    .signup:hover,.button-primary:hover{background:#08b985;transform:translateY(-1px)}
    .menu-toggle{display:none;border:0;background:#0a9f70;border-radius:10px;color:white;font-size:25px;cursor:pointer}
    .login-modal{position:fixed;inset:0;z-index:30;display:none;place-items:center;padding:20px;background:linear-gradient(180deg,rgba(0,25,29,.9) 0%,rgba(4,25,31,.88) 53%,rgba(67,70,82,.91) 54%,rgba(67,70,82,.91) 100%);backdrop-filter:blur(8px)}
    .login-modal.open{display:grid}
    .login-dialog{position:relative;width:min(100%,505px);padding:36px 38px;background:#fbfbfc;border:1px solid rgba(255,255,255,.72);border-radius:28px;box-shadow:0 28px 80px #061d1845;color:#111a30}
    .login-brand{display:flex;align-items:center;gap:13px;margin-bottom:28px}
    .login-brand .brand-mark{width:56px;height:56px;flex:none}
    .login-brand-copy{display:flex;flex-direction:column;align-items:flex-start;gap:2px}
    .login-brand-name{font-size:20px;line-height:1.2;font-weight:800;letter-spacing:-.5px;color:#111a30}
    .login-brand-title{display:block;font-size:13px;line-height:1.35;color:#8799b3}
    .login-heading{font-size:28px;letter-spacing:-.9px;margin-bottom:5px;color:#111a30}
    .login-dialog>p{color:#71819b;font-size:13px;line-height:1.55;margin-bottom:23px}
    .login-close{position:absolute;right:26px;top:26px;width:38px;height:38px;border:0;border-radius:50%;background:#f0f3f7;color:#66809c;font-size:23px;cursor:pointer;transition:background .18s,color .18s}
    .login-close:hover{background:#f1f7f4;color:var(--green)}
    .login-field{display:block;margin-top:17px;color:#33445e;font-size:13px;font-weight:700}
    .login-field input{display:block;width:100%;height:52px;margin-top:7px;padding:0 18px;border:1px solid #d8e2e9;border-radius:13px;background:#fff;color:#172033;font:inherit;font-size:15px;font-weight:400;outline:none}
    .login-field input:focus{border-color:var(--green);box-shadow:0 0 0 3px #07845f20}
    .password-wrap{position:relative;margin-top:7px}
    .login-field .password-wrap input{margin:0;padding-right:54px}
    .password-toggle{position:absolute;right:9px;top:50%;transform:translateY(-50%);width:36px;height:36px;border:0;background:transparent;color:#8a9db5;display:grid;place-items:center;cursor:pointer}
    .password-toggle svg{width:20px;height:20px}
    .forgot-row{display:flex;justify-content:flex-end;margin-top:12px;font-size:13px;font-weight:700;color:#00a879}
    .forgot-row a:hover,.signup-prompt a:hover{color:#087653;text-decoration:underline}
    .login-submit{width:100%;height:58px;margin-top:22px;border:0;border-radius:13px;background:#079b70;color:white;font:inherit;font-size:16px;font-weight:800;cursor:pointer;box-shadow:0 9px 17px #07845f35;transition:transform .18s,box-shadow .18s,background .18s}
    .login-submit:hover{transform:translateY(-1px);box-shadow:0 10px 20px #07845f35}
    .signup-prompt{text-align:center;color:#71819b;font-size:13px;margin-top:27px}
    .signup-prompt a{color:#009d70;font-weight:800}
    .login-error{padding:10px 12px;border-radius:9px;background:#fff0f0;color:#b42318;font-size:13px;margin-bottom:12px}
    .login-success{padding:10px 12px;border-radius:9px;background:#e9f8ef;color:#167347;font-size:13px;margin-bottom:12px}
    body.modal-open{overflow:hidden}
    .hero{min-height:620px;background:radial-gradient(ellipse at 78% 34%,rgba(79,210,147,.12),transparent 37%),linear-gradient(125deg,#fff 0%,#fbfefc 48%,#f2faf5 100%);color:#173d30;display:grid;align-items:center;padding:76px 24px;position:relative;isolation:isolate;overflow:hidden}
    .hero:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 22%,rgba(21,155,102,.065),transparent 25%),radial-gradient(circle at 88% 55%,rgba(62,192,127,.09),transparent 30%);pointer-events:none;z-index:-1}
    .hero:after{content:"";position:absolute;z-index:-1;left:-4%;right:-4%;bottom:-19%;height:42%;border-radius:50% 50% 0 0 / 34% 34% 0 0;background:linear-gradient(110deg,#e1f5e8 0%,#c9efd7 52%,#e9f8ed 100%);transform:rotate(-2deg);pointer-events:none}
    .hero-layout{position:relative;z-index:1;width:min(1220px,100%);margin:auto;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);align-items:center;gap:34px}
    .hero-content{position:relative;z-index:2;max-width:650px;text-align:left}
    .hero-art{position:relative;display:flex;align-items:center;justify-content:center;min-width:0}
    .hero-art:before{content:"";position:absolute;inset:14% 5%;border-radius:50%;background:radial-gradient(ellipse,rgba(92,220,150,.3),rgba(93,197,132,.1) 48%,rgba(34,191,128,0) 72%);filter:blur(28px)}
    .hero-art img{position:relative;z-index:1;display:block;width:100%;max-width:620px;height:auto;filter:drop-shadow(0 28px 38px rgba(31,87,57,.2));animation:hero-float 6s ease-in-out infinite;transition:filter .25s,transform .25s}
    .hero-art:hover img{transform:translateY(-5px) scale(1.015);filter:drop-shadow(0 34px 44px rgba(31,87,57,.27))}
    .hero-kicker{display:inline-flex;align-items:center;gap:10px;margin-bottom:24px;padding:9px 14px;border:1px solid #d7eee0;border-radius:999px;background:rgba(240,250,244,.9);color:#187c56;font-size:11px;font-weight:800;letter-spacing:1.15px;text-transform:uppercase;box-shadow:0 5px 18px rgba(42,111,72,.07);backdrop-filter:blur(10px)}
    .hero-kicker-dot{width:8px;height:8px;border-radius:50%;background:#39e1a6;box-shadow:0 0 0 4px rgba(57,225,166,.14),0 0 16px rgba(57,225,166,.8);animation:kicker-pulse 2s ease-in-out infinite}
    @keyframes hero-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
    @keyframes kicker-pulse{0%,100%{box-shadow:0 0 0 4px rgba(57,225,166,.12),0 0 12px rgba(57,225,166,.55)}50%{box-shadow:0 0 0 7px rgba(57,225,166,.04),0 0 20px rgba(57,225,166,.9)}}
    .badge{display:inline-flex;align-items:center;gap:9px;color:#67e7b7;border:1px solid #155a4c;background:#071e22;border-radius:30px;padding:11px 21px;font-size:13px;font-weight:800;margin-bottom:38px}
    .badge-dot{width:8px;height:8px;background:#16a878;border-radius:50%}
    .hero h1{font-size:clamp(44px,5.1vw,68px);font-weight:800;letter-spacing:-3.2px;line-height:1.12;margin:0 0 25px;padding:0 0 .08em;overflow:visible;color:#173d30}
    .hero h1 span{display:block;margin-top:2px;background:linear-gradient(95deg,#168b5d 0%,#05aa72 52%,#39bd84 100%);background-clip:text;-webkit-background-clip:text;color:transparent}
    .hero p{font-size:18px;line-height:1.65;color:#557466;max-width:570px;margin:0 0 34px}
    .hero-buttons{display:flex;justify-content:flex-start;gap:14px}
    .button{display:inline-flex;align-items:center;justify-content:center;border-radius:14px;padding:17px 32px;min-width:156px;font-size:15px;font-weight:750;transition:background .2s,transform .2s,box-shadow .2s,border-color .2s}
    .button:hover{transform:translateY(-2px)}
    .button-primary{background:linear-gradient(110deg,#25c98a,#079c6d);color:#fff;box-shadow:0 12px 28px rgba(14,160,105,.22),inset 0 1px rgba(255,255,255,.35)}
    .button-primary:hover{box-shadow:0 16px 34px rgba(14,160,105,.3),inset 0 1px rgba(255,255,255,.5)}
    .button-secondary{border:1px solid #d6e9dc;background:rgba(255,255,255,.82);color:#24533e;box-shadow:0 6px 18px rgba(26,82,52,.06);backdrop-filter:blur(8px)}
    .button-secondary:hover{background:#f0faf3;border-color:#bde4ca}
    .section{padding:96px 0}
    .section-heading{text-align:center;max-width:700px;margin:0 auto 58px}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;color:#13845b;font-size:12px;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin-bottom:13px}
    h2{font-size:36px;line-height:1.2;letter-spacing:-1.2px;font-weight:800}
    .section-intro{font-size:16px;line-height:1.7;color:#617b6e;margin-top:13px}
    .workflow,.about{color:#17392b}
    .workflow{position:relative;overflow:hidden;background:linear-gradient(180deg,#fbfefc 0%,#eff9f2 100%)}
    .workflow:before{content:"";position:absolute;width:420px;height:420px;border-radius:50%;right:-210px;top:-200px;background:radial-gradient(circle,#bcebd0 0%,rgba(188,235,208,0) 70%);pointer-events:none}
    .workflow .container{position:relative;z-index:1}
    .workflow .eyebrow,.about .eyebrow{padding:8px 13px;border:1px solid #cdebd8;border-radius:999px;background:#f0fbf4}
    .workflow h2,.about h2,.step h3,.feature-card h3{color:#17392b}
    .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:22px}
    .step{position:relative;min-height:270px;overflow:hidden;border:1px solid #d9ede0;background:rgba(255,255,255,.94);border-radius:24px;padding:32px 27px;text-align:center;box-shadow:0 12px 30px rgba(24,83,51,.07);transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
    .step:before{content:"";position:absolute;inset:0 0 auto;height:4px;background:linear-gradient(90deg,#18a66d,#87d89f)}
    .step:hover{transform:translateY(-6px);border-color:#a9ddbd;box-shadow:0 20px 38px rgba(24,83,51,.13)}
    .step-number{height:54px;width:54px;border-radius:18px;background:linear-gradient(145deg,#dcf8e6,#c7f0d5);color:#08764f;display:grid;place-items:center;font-size:18px;font-weight:850;margin:0 auto 25px;box-shadow:inset 0 0 0 1px #b8e7c9}
    .step h3,.feature-card h3{font-size:19px;letter-spacing:-.4px;margin-bottom:12px}
    .step p,.feature-card p{font-size:14px;line-height:1.7;color:#5b7467}
    .about{position:relative;background:linear-gradient(140deg,#f0f8f2 0%,#f9fcf8 52%,#e8f5eb 100%);border-top:1px solid #dceee1;border-bottom:1px solid #dceee1;padding:104px 0}
    .about-grid{display:grid;grid-template-columns:1.04fr 1fr;gap:48px;align-items:center}
    .about-copy h2{max-width:550px;margin-bottom:22px}
    .about-copy>p{font-size:15.5px;line-height:1.75;color:#587365}
    .stats{display:grid;grid-template-columns:1fr 1fr;gap:13px;margin-top:27px;border:0;padding:0}
    .stat{padding:17px 18px;border:1px solid #d7ebdd;border-radius:17px;background:rgba(255,255,255,.78)}
    .stat strong{display:block;color:#118356;font-size:29px;letter-spacing:-1px}
    .stat span{color:#587365;font-size:13px;line-height:1.45}
    .about-panel{position:relative;overflow:hidden;min-height:356px;padding:42px;border:1px solid rgba(255,255,255,.17);border-radius:28px;background:radial-gradient(circle at 100% 0%,rgba(88,205,144,.3),transparent 38%),linear-gradient(140deg,#075744,#064333);color:#fff;box-shadow:0 24px 48px rgba(5,62,39,.19);display:flex;flex-direction:column;justify-content:center}
    .about-panel:after{content:"";position:absolute;width:200px;height:200px;border:1px solid rgba(167,232,190,.16);border-radius:50%;right:-82px;bottom:-110px;box-shadow:0 0 0 22px rgba(167,232,190,.04),0 0 0 45px rgba(167,232,190,.03)}
    .shield{width:58px;height:58px;border:1px solid #309978;border-radius:18px;color:#a7f1c4;background:linear-gradient(145deg,#0c8061,#08604b);display:grid;place-items:center;margin-bottom:27px;box-shadow:0 8px 20px rgba(0,0,0,.16)}
    .shield svg{width:28px;height:28px}
    .about-panel h3{font-size:24px;line-height:1.32;letter-spacing:-.5px;margin-bottom:14px}
    .about-panel p{font-size:14px;line-height:1.7;color:#c5dfd7}
    .panel-foot{color:#78dfb7!important;font-weight:700;margin-top:25px}
    .team-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;width:min(100%,1160px);margin:42px auto 0}
    .team-card{display:flex;align-items:center;gap:18px;min-width:0;padding:22px;border:1px solid #d9ebe0;border-radius:22px;background:rgba(255,255,255,.92);box-shadow:0 12px 28px rgba(20,66,43,.08);transition:transform .2s ease,box-shadow .2s ease}
    .team-card:hover{transform:translateY(-4px);box-shadow:0 18px 34px rgba(20,66,43,.13)}
    .team-photo{width:112px;height:112px;flex:0 0 112px;object-fit:cover;object-position:center 38%;border:4px solid #d9f5e9;border-radius:50%;background:#eaf4ef}
    .team-details .eyebrow{margin-bottom:7px}
    .team-details h3{margin:0;color:#14231d;font-size:21px;line-height:1.3;letter-spacing:-.4px}
    .team-role{margin-top:5px;color:#52627a;font-size:14px;line-height:1.5}
    .team-achievement{display:inline-flex;align-items:flex-start;gap:8px;margin-top:12px;padding:8px 12px;border-radius:16px;background:#e7f8f0;color:#087456;font-size:12px;font-weight:700;line-height:1.35}
    .team-achievement-copy{display:grid;gap:2px;min-width:0}
    .team-achievement-copy strong{font-size:12px;font-weight:850;white-space:nowrap}
    .team-achievement-copy span{font-size:11px;font-weight:650;line-height:1.3}
    .features{position:relative;overflow:hidden;padding:100px 0;background:linear-gradient(180deg,#f8fcf8 0%,#eaf6ed 100%);color:#17392b}
    .features:before{content:"";position:absolute;width:380px;height:380px;border-radius:50%;left:-220px;bottom:-220px;background:radial-gradient(circle,#c9efd4 0%,rgba(201,239,212,0) 70%);pointer-events:none}
    .features .container{position:relative;z-index:1}
    .features h2{color:#17392b}
    .features .eyebrow{color:#13845b;padding:8px 13px;border:1px solid #cdebd8;border-radius:999px;background:#f0fbf4}
    .feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
    .feature-card{position:relative;overflow:hidden;border:1px solid #d9ede0;background:rgba(255,255,255,.95);border-radius:24px;min-height:247px;padding:32px;box-shadow:0 12px 30px rgba(24,83,51,.07);transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
    .feature-card:after{content:"";position:absolute;width:120px;height:120px;right:-65px;top:-65px;border-radius:50%;background:#e9f8ed;transition:transform .25s ease}
    .feature-card:hover{transform:translateY(-6px);border-color:#a9ddbd;box-shadow:0 20px 38px rgba(24,83,51,.13)}
    .feature-card:hover:after{transform:scale(1.35)}
    .feature-icon{position:relative;z-index:1;width:52px;height:52px;border-radius:17px;background:linear-gradient(145deg,#dff8e8,#c6efd4);color:#08764f;display:grid;place-items:center;margin-bottom:27px;box-shadow:inset 0 0 0 1px #b8e7c9}
    .feature-icon svg{width:22px;height:22px}
    .contact{position:relative;overflow:hidden;background:linear-gradient(145deg,#eaf6ed,#f8fcf8 60%,#e3f3e8);color:#17392b;padding:94px 20px}
    .contact:before,.contact:after{content:"";position:absolute;width:250px;height:250px;border:1px solid rgba(20,132,91,.11);border-radius:50%;pointer-events:none}
    .contact:before{left:-140px;top:-120px;box-shadow:0 0 0 22px rgba(20,132,91,.025),0 0 0 48px rgba(20,132,91,.02)}
    .contact:after{right:-140px;bottom:-150px;box-shadow:0 0 0 22px rgba(20,132,91,.025),0 0 0 48px rgba(20,132,91,.02)}
    .contact-card{position:relative;z-index:1;width:min(100%,720px);margin:auto;padding:44px;border:1px solid #d8ebde;border-top:4px solid #18a66d;border-radius:26px;background:rgba(255,255,255,.97);box-shadow:0 24px 55px rgba(24,83,51,.12)}
    .contact-head{text-align:center;margin-bottom:30px}
    .contact-badge{display:inline-flex;align-items:center;margin-bottom:13px;padding:8px 14px;border:1px solid #cdebd8;border-radius:20px;background:#effaf3;color:#08764f;font-size:11px;font-weight:850;letter-spacing:.9px}
    .contact-head h2{font-size:32px;color:#17392b}
    .contact-head p{max-width:500px;margin:10px auto 0;color:#5b7467;font-size:14px;line-height:1.7}
    .contact-alert{margin:0 0 18px;padding:12px 14px;border-radius:11px;font-size:13px;line-height:1.5}
    .contact-alert.success{background:#e9f8ef;color:#167347}
    .contact-alert.error{background:#fff0f0;color:#b42318}
    .contact-form{display:grid;gap:16px}
    .contact-field{display:grid;gap:8px;color:#385647;font-size:11px;font-weight:850;text-transform:uppercase;letter-spacing:.45px}
    .contact-field input,.contact-field textarea{width:100%;border:1px solid #d8e9dd;border-radius:13px;background:#fbfefb;padding:13px 15px;color:#17392b;font:500 14px 'Plus Jakarta Sans',sans-serif;outline:none;transition:border-color .18s,box-shadow .18s,background .18s}
    .contact-field input{height:47px}
    .contact-field textarea{min-height:105px;resize:vertical}
    .contact-field input::placeholder,.contact-field textarea::placeholder{color:#91a99a;font-weight:400}
    .contact-field input:focus,.contact-field textarea:focus{border-color:#00a879;box-shadow:0 0 0 3px #00a8791c}
    .contact-submit{height:53px;border:0;border-radius:14px;background:linear-gradient(110deg,#20b979,#07885b);color:#fff;font:800 14px 'Plus Jakarta Sans',sans-serif;cursor:pointer;box-shadow:0 10px 22px rgba(14,145,87,.2),inset 0 1px rgba(255,255,255,.3);transition:filter .18s,transform .18s,box-shadow .18s}
    .contact-submit:hover{filter:brightness(1.04);transform:translateY(-2px);box-shadow:0 14px 26px rgba(14,145,87,.25)}
    footer{background:#10182e;color:#fff;padding:32px 0}
    .footer-row{display:flex;align-items:center;justify-content:space-between;gap:24px}
    .footer-brand{display:flex;align-items:center;gap:12px;font-weight:700}
    .footer-brand .brand-mark{height:48px;width:48px}
    .copyright{color:#77839c;font-size:12px;text-align:center}
    .footer-links{display:flex;gap:26px;color:#a0aec1;font-size:13px;font-weight:700}
    @media(max-width:900px){.brand{min-width:auto;padding:8px 12px}.nav-links{gap:7px;padding:6px}.nav-links a{padding:11px 16px}.nav-actions{gap:9px}.hero-layout{grid-template-columns:minmax(0,1fr) minmax(270px,.85fr);gap:24px}.hero h1{font-size:clamp(42px,5.3vw,58px);letter-spacing:-2.8px}.hero p{font-size:16px}.hero-buttons{flex-wrap:wrap}.steps{grid-template-columns:repeat(2,1fr)}.about-grid{gap:30px}.feature-grid{gap:18px}.feature-card{padding:25px}}
    @media(max-width:520px){.login-modal{padding:14px}.login-dialog{padding:30px 24px;border-radius:23px;max-height:calc(100vh - 28px);overflow-y:auto}.login-brand{margin-bottom:24px}.login-close{right:18px;top:18px}.login-heading{font-size:25px}}
    @media(max-width:650px){.contact{padding:64px 15px}.contact-card{padding:29px 22px;border-radius:20px}.contact-head h2{font-size:27px}}
    @media(max-width:650px){.container{width:min(100% - 30px,1220px)}.site-header{height:82px}.brand{gap:9px;padding:6px 9px;border-radius:15px}.brand-mark{width:48px;height:48px}.brand-name{font-size:18px}.brand-tag{font-size:8px}.menu-toggle{display:block;margin-left:auto;width:40px;height:40px}.nav{gap:8px}.nav-links{display:none;position:absolute;left:15px;right:15px;top:75px;background:rgba(255,255,255,.98);padding:14px;border:1px solid #dceee2;border-radius:16px;box-shadow:0 18px 38px rgba(22,69,43,.14);flex-direction:column;align-items:stretch;gap:8px}.nav-links.open{display:flex}.nav-links a{text-align:center}.nav-actions{gap:7px;font-size:12px}.login-link{padding:9px 12px}.signup{padding:9px 13px}.hero{min-height:0;padding:68px 0 54px}.hero-layout{width:min(100% - 30px,600px);grid-template-columns:1fr;gap:28px}.hero-content{text-align:center;margin:0 auto}.hero-art img{width:min(100%,520px)}.badge{font-size:10px;padding:9px 13px;margin-bottom:30px}.hero h1{font-size:clamp(39px,11vw,58px);letter-spacing:-2.6px;line-height:1.12;padding-bottom:.08em}.hero p{font-size:15px;margin:0 auto 28px}.hero-buttons{justify-content:center;gap:10px}.button{min-width:0;padding:14px 18px;font-size:13px}.section,.features{padding:72px 0}.section-heading{margin-bottom:38px}h2{font-size:30px}.steps,.feature-grid{grid-template-columns:1fr}.step{min-height:0;padding:27px 24px}.about{padding:76px 0}.about-grid{grid-template-columns:1fr}.about-panel{min-height:310px;padding:30px}.footer-row{flex-direction:column;text-align:center}.footer-links{order:3}.copyright{order:2;line-height:1.6}}
    @media(max-width:1050px){.team-grid{grid-template-columns:repeat(2,minmax(0,1fr));width:min(100%,980px)}}
    @media(max-width:900px){.team-card{padding:18px;gap:15px}.team-photo{width:96px;height:96px;flex-basis:96px}}
    @media(max-width:650px){.team-grid{grid-template-columns:1fr;margin-top:30px}.team-card{gap:15px;padding:18px}.team-photo{width:88px;height:88px;flex-basis:88px}.team-details h3{font-size:18px}.team-role{font-size:13px}.team-achievement{font-size:11px;padding:6px 9px}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*:before,*:after{transition:none!important;animation:none!important}}
  </style>
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="#home" aria-label="DisBasura home">
      <img class="brand-mark" src="/disbasura/assets/images/disbasura-logo.png" alt="DisBasura logo">
      <span><span class="brand-name">DisBasura</span><span class="brand-tag">Smart Garbage Collection</span></span>
    </a>
    <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
    <nav class="nav-links" aria-label="Main navigation"><a href="#home">HOME</a><a href="#about">ABOUT US</a><a href="#services">SERVICES</a><a href="#contacts">CONTACTS</a></nav>
    <div class="nav-actions"><a class="login-link" href="#login" data-open-login>Sign in</a><a class="signup" href="/disbasura/register.php">Sign up</a></div>
  </div>
</header>
<main>
  <section class="hero" id="home">
    <div class="hero-layout">
      <div class="hero-content">
        <div class="hero-kicker"><span class="hero-kicker-dot"></span>Community-powered waste collection</div>
        <h1>Smart &amp; Efficient<span>Garbage Collection</span><span>System</span></h1>
        <p>A centralized barangay-level waste collection platform connecting administrators, sitio residents, and truck collectors seamlessly.</p>
        <div class="hero-buttons"><a class="button button-primary" href="<?= $registrationEnabled ? '/disbasura/register.php' : '/disbasura/admin/setup.php' ?>"><?= $registrationEnabled ? 'Get Started' : 'Set Up Admin' ?></a><a class="button button-secondary" href="#workflow">How It Works</a></div>
      </div>
      <div class="hero-art"><img src="/disbasura/assets/images/collection-hero.png" alt="A collection truck and collector working with recycling mascots"></div>
    </div>
  </section>
  <section class="section workflow" id="workflow">
    <div class="container">
      <div class="section-heading"><p class="eyebrow">Simplified workflow</p><h2>How to Use This System</h2><p class="section-intro">Connecting community leaders, residents, and collection crews through a unified digital platform.</p></div>
      <div class="steps">
        <article class="step"><div class="step-number">1</div><h3>Admin Sets Up</h3><p>Barangay admin configures sitio zones, adds truck collector accounts, and establishes weekly collection schedules.</p></article>
        <article class="step"><div class="step-number">2</div><h3>Residents Register</h3><p>Residents sign up under their designated sitio to view collection timetables and submit special pickup requests.</p></article>
        <article class="step"><div class="step-number">3</div><h3>Collector Goes Live</h3><p>Collectors log in during route runs, enable real-time GPS tracking, and upload photo proof upon completion.</p></article>
        <article class="step"><div class="step-number">4</div><h3>Admin Monitors</h3><p>Live dashboard provides visibility over active trucks, completed pickups, missed disputes, and system logs.</p></article>
      </div>
    </div>
  </section>
  <section class="about" id="about">
    <div class="container">
      <div class="about-grid">
      <div class="about-copy"><p class="eyebrow">About DisBasura</p><h2>Empowering Barangays with Smart Eco-Tech Innovations</h2><p>DisBasura is a comprehensive capstone initiative developed for local barangay waste management. By replacing inefficient traditional garbage collection schedules with a digitized real-time dispatch, tracking, and reporting platform, we minimize missed collections and optimize urban cleanliness.</p><div class="stats"><div class="stat"><strong>100%</strong><span>Digital Accountability</span></div><div class="stat"><strong>Real-Time</strong><span>GPS Dispatching</span></div></div></div>
      <aside class="about-panel"><div class="shield"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 19 6v5c0 4.7-2.8 8-7 10-4.2-2-7-5.3-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.7"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Cleaner Neighborhoods, Transparent Governance</h3><p>Our centralized database keeps sitio leaders, local residents, and truck operators on the same page. Photo proof uploads ensure zero ambiguity when collections are marked completed.</p><p class="panel-foot">Tested for Barangay Community Deployment&nbsp; ✓</p></aside>
      </div>
      <div class="team-grid" aria-label="DisBasura project team">
        <article class="team-card">
          <img class="team-photo" src="/disbasura/assets/images/team/rey-lendon-panares.jpg" alt="Pañares, Rey Lendon G." loading="lazy"/>
          <div class="team-details">
            <p class="eyebrow">Project Team</p>
            <h3>Pañares, Rey Lendon G.</h3>
            <p class="team-role">Project Manager/Lead Developer</p>
            <span class="team-achievement" title="Top 25 in the Philippine Startup Challenge XI, Central Visayas Regional Competition"><span aria-hidden="true">🏆</span><span class="team-achievement-copy"><strong>Top 25</strong><span>Philippine Startup Challenge XI<br>Central Visayas Regional Competition</span></span></span>
          </div>
        </article>
        <article class="team-card">
          <img class="team-photo" src="/disbasura/assets/images/team/brent-john-paller.jpg" alt="Paller, Brent John B." loading="lazy"/>
          <div class="team-details">
            <p class="eyebrow">Project Team</p>
            <h3>Paller, Brent John B.</h3>
            <p class="team-role">UI/UX / Troubleshooter</p>
            <span class="team-achievement" title="Top 25 in the Philippine Startup Challenge XI, Central Visayas Regional Competition"><span aria-hidden="true">🏆</span><span class="team-achievement-copy"><strong>Top 25</strong><span>Philippine Startup Challenge XI<br>Central Visayas Regional Competition</span></span></span>
          </div>
        </article>
        <article class="team-card">
          <img class="team-photo" src="/disbasura/assets/images/team/joseph-rey-mart-paradilla.jpg" alt="Paradilla, Joseph Rey Mart M." loading="lazy"/>
          <div class="team-details">
            <p class="eyebrow">Project Team</p>
            <h3>Paradilla, Joseph Rey Mart M.</h3>
            <p class="team-role">QA Tester / Database / Documentation</p>
            <span class="team-achievement" title="Top 25 in the Philippine Startup Challenge XI, Central Visayas Regional Competition"><span aria-hidden="true">🏆</span><span class="team-achievement-copy"><strong>Top 25</strong><span>Philippine Startup Challenge XI<br>Central Visayas Regional Competition</span></span></span>
          </div>
        </article>
      </div>
    </div>
  </section>
  <section class="features" id="services">
    <div class="container"><div class="section-heading"><p class="eyebrow">Our Features</p><h2>Comprehensive Waste Services</h2></div>
      <div class="feature-grid">
        <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></div><h3>Sitio-Based Scheduling</h3><p>Organized waste pickup routes tailored specifically per sitio, preventing overflow and overlapping garbage truck schedules.</p></article>
        <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg></div><h3>Special Pickup Requests</h3><p>Residents can request special bulk disposal pickups directly through the portal with instant admin approval tracking.</p></article>
        <article class="feature-card"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/></svg></div><h3>Dispute &amp; Missed Resolution</h3><p>Instant reporting for missed collections helps barangay captains resolve residents' concerns quickly.</p></article>
      </div>
    </div>
  </section>
  <section class="contact" id="contacts">
    <div class="contact-card">
      <div class="contact-head"><span class="contact-badge">GET IN TOUCH</span><h2>Contact Us</h2><p>Have questions about implementing DisBasura in your barangay or experiencing collection issues? Send us a message!</p></div>
      <?php if (($_GET['contact'] ?? '') === 'verified'): ?><div class="contact-alert success" role="status">Your Gmail address is confirmed. The admin can now review your message and reply to that inbox.</div><?php endif; ?>
      <?php if ($contactError !== ''): ?><div class="contact-alert error" role="alert"><?= htmlspecialchars($contactError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <?php if ($contactPending): ?>
      <div class="contact-alert success" role="status">We sent a 6-digit verification code to your Gmail address. Enter it below to confirm your message.</div>
      <form class="contact-form" action="/disbasura/index.php?contact=verify" method="post">
        <input type="hidden" name="contact_submit" value="1">
        <input type="hidden" name="contact_csrf" value="<?= htmlspecialchars($_SESSION['contact_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <label class="contact-field">Gmail Verification Code<input type="text" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter the 6-digit code" required></label>
        <button class="contact-submit" type="submit" name="contact_verify" value="1">Verify Gmail and Send Message</button>
      </form>
      <?php else: ?>
      <form class="contact-form" action="/disbasura/index.php" method="post">
        <input type="hidden" name="contact_submit" value="1">
        <input type="hidden" name="contact_csrf" value="<?= htmlspecialchars($_SESSION['contact_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <label class="contact-field">Full Name<input type="text" name="full_name" placeholder="e.g. Juan Dela Cruz" maxlength="120" value="<?= htmlspecialchars($contactValues['full_name'], ENT_QUOTES, 'UTF-8') ?>" required></label>
        <label class="contact-field">Email Address<input type="email" name="email" placeholder="name@gmail.com" maxlength="190" value="<?= htmlspecialchars($contactValues['email'], ENT_QUOTES, 'UTF-8') ?>" required></label>
        <label class="contact-field">Sitio / Area<input type="text" name="area" placeholder="e.g. Sitio Central" maxlength="160" value="<?= htmlspecialchars($contactValues['area'], ENT_QUOTES, 'UTF-8') ?>"></label>
        <label class="contact-field">Message / Issue<textarea name="message" placeholder="How can we help you?" maxlength="3000" required><?= htmlspecialchars($contactValues['message'], ENT_QUOTES, 'UTF-8') ?></textarea></label>
        <button class="contact-submit" type="submit">Send Message</button>
      </form>
      <?php endif; ?>
    </div>
  </section>
</main>
<div class="login-modal" id="login-modal" aria-hidden="true">
  <section class="login-dialog" role="dialog" aria-modal="true" aria-labelledby="login-title">
    <button class="login-close" type="button" aria-label="Close sign in" data-close-login>&times;</button>
    <div class="login-brand">
      <img class="brand-mark" src="/disbasura/assets/images/disbasura-logo.png" alt="DisBasura logo">
      <span class="login-brand-copy"><span class="login-brand-name">DisBasura</span><span class="login-brand-title">Smart Garbage Collection</span></span>
    </div>
    <h2 class="login-heading" id="login-title">Welcome back</h2>
    <p>Sign in with your account. We’ll take you to the right dashboard.</p>
    <?php if (!empty($_GET['registered'])): ?><div class="login-success">Account created successfully. Sign in to continue.</div><?php endif; ?>
    <?php if (!empty($_GET['login_error'])): ?><div class="login-error"><?= htmlspecialchars($_GET['login_error'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="/disbasura/login.php">
      <label class="login-field">Username<input name="username" placeholder="Enter your username" required autocomplete="username"></label>
      <label class="login-field">Password
        <span class="password-wrap">
          <input id="login-password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
          <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-toggle-password>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </span>
      </label>
      <div class="forgot-row"><a href="/disbasura/forgot-password.php">Forgot password?</a></div>
      <button class="login-submit" type="submit">Sign in</button>
    </form>
    <div class="signup-prompt">Don't have an account? <a href="/disbasura/register.php">Sign up</a></div>
  </section>
</div>
<footer><div class="container footer-row"><a class="footer-brand" href="#home"><img class="brand-mark" src="/disbasura/assets/images/disbasura-logo.png" alt="DisBasura logo">DisBasura</a><p class="copyright">© 2026 DisBasura Capstone System. All rights reserved.</p><nav class="footer-links" aria-label="Footer navigation"><a href="#home">Home</a><a href="#about">About</a><a href="#services">Service</a></nav></div></footer>
<script>
  const menuButton=document.querySelector('.menu-toggle');
  const navLinks=document.querySelector('.nav-links');
  menuButton.addEventListener('click',()=>{const open=navLinks.classList.toggle('open');menuButton.setAttribute('aria-expanded',String(open));});
  navLinks.addEventListener('click',event=>{const link=event.target.closest('a');if(link){navLinks.querySelectorAll('a').forEach(item=>item.classList.remove('active'));link.classList.add('active');navLinks.classList.remove('open');menuButton.setAttribute('aria-expanded','false');}});
  const loginModal=document.getElementById('login-modal');
  const openLogin=()=>{loginModal.classList.add('open');loginModal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');loginModal.querySelector('input').focus();};
  const closeLogin=()=>{loginModal.classList.remove('open');loginModal.setAttribute('aria-hidden','true');document.body.classList.remove('modal-open');};
  document.querySelectorAll('[data-open-login]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();openLogin();}));
  document.querySelector('[data-close-login]').addEventListener('click',closeLogin);
  const passwordToggle=document.querySelector('[data-toggle-password]');
  passwordToggle.addEventListener('click',()=>{const password=document.getElementById('login-password');const visible=password.type==='password';password.type=visible?'text':'password';passwordToggle.setAttribute('aria-label',visible?'Hide password':'Show password');passwordToggle.setAttribute('aria-pressed',String(visible));});
  loginModal.addEventListener('click',event=>{if(event.target===loginModal)closeLogin();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&loginModal.classList.contains('open'))closeLogin();});
  const query=new URLSearchParams(location.search);
  if(query.has('show_login')||query.has('login_error')||query.has('registered'))openLogin();
  document.addEventListener('submit',async event=>{
    const form=event.target.closest('#contacts .contact-card form.contact-form');
    if(!form)return;
    event.preventDefault();
    const submitButton=form.querySelector('button[type="submit"]');
    if(submitButton){submitButton.disabled=true;submitButton.dataset.originalText=submitButton.textContent;submitButton.textContent='Sending…';}
    try{
      const formData=new FormData(form);
      if(event.submitter&&event.submitter.name)formData.append(event.submitter.name,event.submitter.value);
      const response=await fetch(form.action,{method:'POST',body:formData,credentials:'same-origin'});
      const html=await response.text();
      const parsed=new DOMParser().parseFromString(html,'text/html');
      const updatedCard=parsed.querySelector('#contacts .contact-card');
      const currentCard=document.querySelector('#contacts .contact-card');
      if(!response.ok||!updatedCard||!currentCard)throw new Error('The response could not be loaded. Please try again.');
      currentCard.replaceWith(updatedCard);
      const codeInput=updatedCard.querySelector('input[name="verification_code"]');
      if(codeInput)codeInput.focus({preventScroll:true});
    }catch(error){
      if(submitButton){submitButton.disabled=false;submitButton.textContent=submitButton.dataset.originalText||'Send Message';}
      let notice=form.querySelector('.contact-submit-error');
      if(!notice){notice=document.createElement('div');notice.className='contact-alert error contact-submit-error';notice.setAttribute('role','alert');form.prepend(notice);}
      notice.textContent=error.message||'Unable to send right now. Please try again.';
    }
  });
</script>
</body>
</html>

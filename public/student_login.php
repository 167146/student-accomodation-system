<?php

session_start();

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $pdo->prepare(
            "SELECT email, name, password, role
             FROM users
             WHERE email = ?
             AND role = 'student'
             LIMIT 1"
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user["password"])) {

            $_SESSION["email"] = $user["email"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["role"] = $user["role"];

            header("Location: ../student/student.php");
            exit;

        } else {

            $error = "Invalid student email or password.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login | UniMtaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
    :root {
        --p1: #2E1065;
        --p2: #4C1D95;
        --p3: #6D28D9;
        --p4: #8B5CF6;
        --p5: #C4B5FD;
        --p6: #EDE9FE;
        --p7: #F7F4FF;
        --ink: #1F1236;
        --muted: #675E80;
        --line: #E6DEF5;
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        min-height: 100vh;
        font-family: "Plus Jakarta Sans", Inter, "Segoe UI", system-ui, Arial, sans-serif;
        color: var(--ink);
        background: #FAF8FF;
        -webkit-font-smoothing: antialiased;
    }

    a {
        color: var(--p3);
        font-weight: 700;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    :focus-visible {
        outline: 3px solid var(--p4);
        outline-offset: 2px;
    }

    .page {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
        min-height: 100vh;
    }

    /* ---------- art panel ---------- */
    .art {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 40px 48px 0;
        background:
            radial-gradient(70% 60% at 85% 10%, rgba(196, 181, 253, .5), transparent 60%),
            linear-gradient(150deg, var(--p1), var(--p2) 50%, var(--p3));
    }

    .art .sky {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 220px;
        z-index: -1;
    }

    .art .sky svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 800;
        font-size: 22px;
        color: #fff;
    }

    .logo:hover {
        text-decoration: none;
    }

    .logo span.mark {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--p4), var(--p3));
        box-shadow: 0 8px 20px -6px rgba(0, 0, 0, .45), inset 0 1px 0 rgba(255, 255, 255, .3);
    }

    .pitch {
        max-width: 480px;
        padding-bottom: 250px;
    }

    .pitch h2 {
        margin: 0 0 14px;
        font-size: clamp(30px, 3.4vw, 46px);
        line-height: 1.08;
        letter-spacing: -.03em;
        font-weight: 800;
    }

    .pitch h2 em {
        font-style: normal;
        color: var(--p5);
    }

    .pitch p {
        margin: 0 0 22px;
        color: #E9E0FF;
        font-size: 16px;
    }

    .perks {
        display: grid;
        gap: 10px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .perks li {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        font-size: 14.5px;
    }

    .perks .tick {
        flex: none;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .28);
    }

    /* ---------- form panel ---------- */
    .panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 24px;
    }

    .box {
        width: 100%;
        max-width: 420px;
    }

    .box .mobile-logo {
        display: none;
    }

    .role-tabs {
        display: inline-flex;
        padding: 5px;
        border-radius: 99px;
        background: var(--p6);
        margin-bottom: 26px;
        font-size: 13px;
    }

    .role-tabs span,
    .role-tabs a {
        padding: 8px 18px;
        border-radius: 99px;
        font-weight: 700;
    }

    .role-tabs span {
        background: #fff;
        color: var(--p2);
        box-shadow: 0 4px 10px -4px rgba(46, 16, 101, .3);
    }

    .role-tabs a {
        color: var(--muted);
    }

    .role-tabs a:hover {
        color: var(--p2);
        text-decoration: none;
    }

    h1 {
        margin: 0 0 6px;
        font-size: 32px;
        letter-spacing: -.025em;
        font-weight: 800;
        color: var(--p1);
    }

    .sub {
        margin: 0 0 24px;
        color: var(--muted);
    }

    .note {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding: 13px 16px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .note.err {
        background: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FCA5A5;
    }

    .note.ok {
        background: #DCFCE7;
        color: #166534;
        border: 1px solid #86EFAC;
    }

    .field {
        margin-bottom: 18px;
    }

    .field label {
        display: block;
        margin-bottom: 7px;
        font-size: 12.5px;
        font-weight: 800;
        color: var(--p2);
    }

    .input {
        position: relative;
    }

    .input svg.lead {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--p4);
        pointer-events: none;
    }

    .input input {
        width: 100%;
        padding: 14px 16px 14px 46px;
        font: inherit;
        font-size: 15px;
        color: var(--ink);
        background: #fff;
        border: 1.5px solid var(--line);
        border-radius: 14px;
        outline: 0;
        transition: border-color .15s, box-shadow .15s;
    }

    .input input:focus {
        border-color: var(--p4);
        box-shadow: 0 0 0 4px rgba(139, 92, 246, .16);
    }

    .eye {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        color: var(--muted);
        padding: 8px;
        border-radius: 10px;
        cursor: pointer;
    }

    .eye:hover {
        background: var(--p6);
        color: var(--p2);
    }

    .submit {
        width: 100%;
        margin-top: 6px;
        padding: 15px;
        border: 0;
        border-radius: 14px;
        cursor: pointer;
        font: inherit;
        font-weight: 800;
        font-size: 15px;
        color: #fff;
        background: linear-gradient(135deg, var(--p3), var(--p2));
        box-shadow: 0 14px 26px -12px rgba(109, 40, 217, .9);
        transition: transform .15s, box-shadow .15s;
    }

    .submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 30px -12px rgba(109, 40, 217, .95);
    }

    .links {
        margin-top: 26px;
        padding-top: 22px;
        border-top: 1px dashed var(--line);
        display: grid;
        gap: 10px;
        font-size: 14px;
        color: var(--muted);
    }

    @media (max-width: 900px) {
        .page {
            grid-template-columns: 1fr;
        }

        .art {
            display: none;
        }

        .panel {
            align-items: flex-start;
            padding-top: 40px;
            background:
                radial-gradient(80% 40% at 50% 0%, rgba(196, 181, 253, .35), transparent 70%),
                #FAF8FF;
        }

        .box .mobile-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            font-weight: 800;
            font-size: 20px;
            color: var(--p1);
        }

        .box .mobile-logo .mark {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, var(--p4), var(--p3));
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            transition: none !important;
        }

        .submit:hover {
            transform: none;
        }
    }
    </style>
</head>

<body>
    <div class="page">

        <aside class="art" aria-hidden="true">
            <div class="sky"><svg viewBox="0 0 1200 170" preserveAspectRatio="xMidYMax slice" aria-hidden="true"
                    focusable="false">
                    <rect x="0" y="106" width="64" height="64" fill="#2E1065" opacity=".55" />
                    <rect x="22" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="36" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="50" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="8" y="132" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="36" y="132" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="8" y="148" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="22" y="148" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="68" y="51" width="90" height="119" fill="#2E1065" opacity=".55" />
                    <rect x="104" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="118" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="104" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="132" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="90" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="132" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="146" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="104" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="118" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="132" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="146" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="104" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="146" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="76" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="172" y="118" width="40" height="52" fill="#2E1065" opacity=".55" />
                    <rect x="194" y="128" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="180" y="160" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="220" y="66" width="40" height="104" fill="#2E1065" opacity=".55" />
                    <rect x="228" y="76" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="242" y="76" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="228" y="92" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="242" y="92" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="242" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="268" y="80" width="78" height="90" fill="#2E1065" opacity=".55" />
                    <rect x="304" y="90" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="318" y="90" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="276" y="106" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="290" y="106" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="304" y="106" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="276" y="122" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="276" y="138" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="332" y="138" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="290" y="154" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="318" y="154" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="332" y="154" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="360" y="57" width="52" height="113" fill="#2E1065" opacity=".55" />
                    <rect x="368" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="396" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="382" y="83" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="396" y="99" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="368" y="131" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="416" y="79" width="90" height="91" fill="#2E1065" opacity=".55" />
                    <rect x="424" y="89" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="466" y="89" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="424" y="105" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="452" y="105" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="494" y="105" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="466" y="121" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="424" y="137" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="424" y="153" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="514" y="97" width="40" height="73" fill="#2E1065" opacity=".55" />
                    <rect x="522" y="107" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="536" y="107" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="536" y="139" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="522" y="155" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="536" y="155" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="558" y="25" width="78" height="145" fill="#2E1065" opacity=".55" />
                    <rect x="580" y="35" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="622" y="35" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="622" y="51" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="566" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="608" y="67" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="580" y="83" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="608" y="83" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="566" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="580" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="594" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="608" y="115" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="566" y="131" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="580" y="131" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="594" y="147" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="622" y="147" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="640" y="125" width="90" height="45" fill="#2E1065" opacity=".55" />
                    <rect x="662" y="135" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="718" y="135" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="704" y="151" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="718" y="151" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="744" y="54" width="78" height="116" fill="#2E1065" opacity=".55" />
                    <rect x="752" y="64" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="794" y="96" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="752" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="766" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="794" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="808" y="112" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="752" y="128" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="794" y="128" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="752" y="144" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="794" y="144" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="794" y="160" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="830" y="72" width="64" height="98" fill="#2E1065" opacity=".55" />
                    <rect x="838" y="82" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="852" y="82" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="866" y="82" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="880" y="98" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="838" y="114" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="866" y="114" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="880" y="114" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="838" y="130" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="902" y="114" width="64" height="56" fill="#2E1065" opacity=".55" />
                    <rect x="910" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="924" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="938" y="124" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="924" y="140" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="938" y="140" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="910" y="156" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="970" y="35" width="90" height="135" fill="#2E1065" opacity=".55" />
                    <rect x="978" y="45" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1006" y="45" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1020" y="45" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="978" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1006" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1048" y="61" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1006" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1020" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1034" y="77" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="978" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1006" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1048" y="109" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="992" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1006" y="125" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="978" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1006" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1020" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1034" y="141" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1006" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1020" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1034" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1048" y="157" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1074" y="94" width="52" height="76" fill="#2E1065" opacity=".55" />
                    <rect x="1096" y="104" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.6" />
                    <rect x="1082" y="120" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1096" y="120" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.35" />
                    <rect x="1110" y="120" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1082" y="136" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1082" y="152" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1134" y="106" width="78" height="64" fill="#2E1065" opacity=".55" />
                    <rect x="1142" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1170" y="116" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1170" y="132" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                    <rect x="1156" y="148" width="6" height="8" rx="1.5" fill="#C4B5FD" opacity="0.9" />
                </svg></div>
            <a class="logo" href="#" tabindex="-1">
                <span class="mark">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 11l9-8 9 8" />
                        <path d="M5 10v10h14V10" />
                        <path d="M10 20v-6h4v6" />
                    </svg>
                </span>
                UniMtaa
            </a>

            <div class="pitch">
                <h2>Find a home you can <em>trust</em>, close to campus.</h2>
                <p>Browse verified student accommodation, compare options and hear from students who stayed there.</p>
                <ul class="perks">
                    <li><span class="tick">&#10003;</span> Listings checked before you see them</li>
                    <li><span class="tick">&#10003;</span> Honest ratings from past tenants</li>
                    <li><span class="tick">&#10003;</span> Message landlords directly</li>
                </ul>
            </div>
        </aside>

        <main class="panel">
            <div class="box">

                <div class="mobile-logo">
                    <span class="mark">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 11l9-8 9 8" />
                            <path d="M5 10v10h14V10" />
                            <path d="M10 20v-6h4v6" />
                        </svg>
                    </span>
                    UniMtaa
                </div>

                <div class="role-tabs" aria-label="Choose login type">
                    <span aria-current="page">Student</span>
                    <a href="landlord_login.php">Landlord</a>
                </div>

                <h1>Student Login</h1>
                <p class="sub">Welcome back. Sign in to continue your search.</p>

                <?php if (!empty($error)): ?>

                <p class="note err" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </p>

                <?php endif; ?>

                <?php if (isset($_GET["registered"])): ?>

                <p class="note ok" role="status">
                    Registration successful. You can now log in.
                </p>

                <?php endif; ?>

                <form method="POST">

                    <div class="field">
                        <label for="email">Email</label>
                        <div class="input">
                            <svg class="lead" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="3" />
                                <path d="M3 8l9 6 9-6" />
                            </svg>
                            <input type="email" id="email" name="email" autocomplete="email"
                                placeholder="you@university.ac.ke" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input">
                            <svg class="lead" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                <rect x="4" y="10" width="16" height="11" rx="3" />
                                <path d="M8 10V7a4 4 0 018 0v3" />
                            </svg>
                            <input type="password" id="password" name="password" autocomplete="current-password"
                                placeholder="Your password" required>
                            <button type="button" class="eye" id="toggle" aria-label="Show password"
                                aria-pressed="false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                    aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="submit">
                        Student Login
                    </button>

                </form>

                <div class="links">
                    <span>
                        Don't have an account?
                        <a href="register.php">
                            Register
                        </a>
                    </span>

                    <span>
                        Are you a landlord?
                        <a href="landlord_login.php">
                            Landlord Login
                        </a>
                    </span>
                </div>

            </div>
        </main>
    </div>

    <script>
    (function() {
        var b = document.getElementById("toggle"),
            i = document.getElementById("password");
        if (!b || !i) return;
        b.addEventListener("click", function() {
            var show = i.type === "password";
            i.type = show ? "text" : "password";
            b.setAttribute("aria-pressed", show ? "true" : "false");
            b.setAttribute("aria-label", show ? "Hide password" : "Show password");
        });
    })();
    </script>
</body>

</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniMtaa | Find Your Mtaa, Find Your Student Home</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <script>
    document.documentElement.classList.add("js");
    </script>

    <style>
    :root {
        /* A family of purples, light to vivid */
        --p50: #FBF8FF;
        --p100: #F4EDFF;
        --p200: #E9DCFF;
        --p300: #D8C2FF;
        --p400: #BE9BFF;
        --p500: #A175F7;
        --p600: #8B5CF6;
        --p700: #7243E0;
        --orchid: #E7C6FF;
        --mauve: #D9A7F0;
        --ink: #3B2C66;
        --ink-soft: #6E5F99;
        --white: #FFFFFF;
        --radius: 26px;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        font-family: "Plus Jakarta Sans", "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
        background: var(--p50);
        color: var(--ink);
        line-height: 1.65;
        overflow-x: hidden;
    }

    a {
        text-decoration: none;
        color: inherit;
    }

    img {
        display: block;
        max-width: 100%;
    }

    .wrap {
        width: min(1160px, 90%);
        margin: 0 auto;
    }

    /* ---------- REVEAL ON SCROLL ---------- */

    .js .reveal {
        opacity: 0;
        transform: translateY(26px);
        transition: opacity 0.7s ease, transform 0.7s ease;
    }

    .js .reveal.in {
        opacity: 1;
        transform: none;
    }

    /* ---------- BUTTONS ---------- */

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 28px;
        border-radius: 999px;
        border: none;
        font-family: inherit;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
    }

    .btn-main {
        color: #fff;
        background: linear-gradient(135deg, var(--p500), var(--p700));
        box-shadow: 0 12px 26px rgba(139, 92, 246, 0.35);
    }

    .btn-main:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(139, 92, 246, 0.45);
    }

    .btn-soft {
        color: var(--p700);
        background: var(--p200);
    }

    .btn-soft:hover {
        background: var(--p300);
    }

    .btn-white {
        color: var(--p700);
        background: #fff;
        box-shadow: 0 12px 26px rgba(114, 67, 224, 0.25);
    }

    .btn-white:hover {
        transform: translateY(-2px);
    }

    /* ---------- FLOATING NAV ---------- */

    .nav-shell {
        position: sticky;
        top: 14px;
        z-index: 60;
        padding: 0 4%;
    }

    .home-nav {
        max-width: 1160px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 10px 12px 10px 22px;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid var(--p200);
        border-radius: 999px;
        box-shadow: 0 10px 30px rgba(139, 92, 246, 0.14);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .brand-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        background: linear-gradient(135deg, var(--p400), var(--p600));
        box-shadow: 0 6px 14px rgba(139, 92, 246, 0.35);
    }

    .brand-name {
        font-size: 19px;
        font-weight: 800;
        letter-spacing: -0.3px;
        color: var(--p700);
        line-height: 1.1;
    }

    .brand-subtitle {
        display: block;
        font-size: 10.5px;
        font-weight: 600;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: var(--ink-soft);
    }

    .nav-links {
        display: flex;
        gap: 6px;
    }

    .nav-links a {
        padding: 9px 16px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        color: var(--ink-soft);
        transition: background 0.2s, color 0.2s;
    }

    .nav-links a:hover {
        background: var(--p100);
        color: var(--p700);
    }

    .nav-buttons {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .nav-buttons .login {
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 700;
        color: var(--p700);
        border-radius: 999px;
    }

    .nav-buttons .login:hover {
        background: var(--p100);
    }

    .nav-buttons .btn {
        padding: 11px 22px;
        font-size: 14px;
    }

    /* ---------- HERO ---------- */

    .hero {
        position: relative;
        padding: 70px 0 110px;
        overflow: hidden;
    }

    .blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(70px);
        z-index: 0;
        pointer-events: none;
    }

    .blob.b1 {
        width: 460px;
        height: 460px;
        background: var(--p200);
        top: -120px;
        left: -140px;
    }

    .blob.b2 {
        width: 380px;
        height: 380px;
        background: var(--orchid);
        top: 120px;
        right: -100px;
        opacity: 0.8;
    }

    .blob.b3 {
        width: 300px;
        height: 300px;
        background: var(--p300);
        bottom: -120px;
        left: 38%;
        opacity: 0.7;
    }

    .hero-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1.08fr 1fr;
        gap: 56px;
        align-items: center;
    }

    .hero-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid var(--p200);
        color: var(--p700);
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 22px;
        box-shadow: 0 6px 16px rgba(139, 92, 246, 0.1);
    }

    .hero h1 {
        font-size: clamp(38px, 5.4vw, 66px);
        line-height: 1.04;
        font-weight: 800;
        letter-spacing: -1.6px;
        margin-bottom: 20px;
    }

    .hero h1 em {
        font-style: normal;
        position: relative;
        display: inline-block;
        color: var(--p600);
    }

    .hero h1 em::after {
        content: "";
        position: absolute;
        left: -4px;
        right: -4px;
        bottom: 4px;
        height: 16px;
        border-radius: 10px;
        background: var(--p300);
        opacity: 0.65;
        z-index: -1;
    }

    .hero-text {
        font-size: 18px;
        color: var(--ink-soft);
        max-width: 500px;
        margin-bottom: 30px;
    }

    /* search card */

    .search-card {
        background: #fff;
        border: 1px solid var(--p200);
        border-radius: 22px;
        padding: 12px;
        display: grid;
        grid-template-columns: 1.3fr 1fr auto;
        gap: 10px;
        max-width: 620px;
        box-shadow: 0 22px 50px rgba(139, 92, 246, 0.18);
    }

    .field {
        display: flex;
        flex-direction: column;
        padding: 8px 14px;
        border-radius: 14px;
        background: var(--p50);
        border: 1px solid transparent;
        transition: border-color 0.2s, background 0.2s;
    }

    .field:focus-within {
        border-color: var(--p400);
        background: #fff;
    }

    .field label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: var(--p600);
    }

    .field input,
    .field select {
        border: none;
        outline: none;
        background: transparent;
        font-family: inherit;
        font-size: 15px;
        font-weight: 600;
        color: var(--ink);
        padding: 3px 0 0;
        width: 100%;
    }

    .field input::placeholder {
        color: #B5A6D8;
        font-weight: 500;
    }

    .search-card .btn {
        padding: 0 26px;
    }

    .quick-chips {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 18px;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink-soft);
    }

    .chip {
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid var(--p300);
        background: rgba(255, 255, 255, 0.7);
        color: var(--p700);
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s, transform 0.15s;
    }

    .chip:hover {
        background: var(--p200);
        transform: translateY(-1px);
    }

    /* collage */

    .collage {
        position: relative;
        height: 560px;
    }

    .collage .arch {
        position: absolute;
        right: 0;
        top: 0;
        width: 76%;
        height: 100%;
        border-radius: 999px 999px 32px 32px;
        overflow: hidden;
        border: 8px solid #fff;
        box-shadow: 0 30px 60px rgba(114, 67, 224, 0.28);
        background: var(--p200);
    }

    .collage .arch img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .collage .round {
        position: absolute;
        left: 0;
        bottom: 70px;
        width: 190px;
        height: 190px;
        border-radius: 50%;
        overflow: hidden;
        border: 7px solid #fff;
        box-shadow: 0 20px 40px rgba(114, 67, 224, 0.25);
        background: var(--p300);
    }

    .collage .round img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .float {
        position: absolute;
        background: #fff;
        border: 1px solid var(--p200);
        border-radius: 16px;
        padding: 12px 16px;
        box-shadow: 0 16px 34px rgba(114, 67, 224, 0.22);
        font-size: 13px;
        font-weight: 700;
        animation: bob 5s ease-in-out infinite;
    }

    .float small {
        display: block;
        font-weight: 500;
        color: var(--ink-soft);
        font-size: 12px;
    }

    .float.f1 {
        top: 46px;
        left: 14px;
    }

    .float.f2 {
        right: -6px;
        bottom: 40px;
        animation-delay: -2s;
    }

    .sticker {
        position: absolute;
        top: -6px;
        right: 6px;
        width: 104px;
        height: 104px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.25;
        color: #fff;
        background: linear-gradient(135deg, var(--mauve), var(--p600));
        box-shadow: 0 14px 28px rgba(139, 92, 246, 0.4);
        transform: rotate(12deg);
        z-index: 3;
    }

    @keyframes bob {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-9px);
        }
    }

    /* ---------- TRUST STRIP ---------- */

    .trust {
        position: relative;
        z-index: 2;
        margin-top: -50px;
    }

    .trust-bar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        background: linear-gradient(120deg, var(--p400), var(--p600));
        border-radius: 28px;
        padding: 26px 18px;
        box-shadow: 0 24px 50px rgba(139, 92, 246, 0.35);
        color: #fff;
    }

    .trust-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 22px;
    }

    .trust-item+.trust-item {
        border-left: 1px solid rgba(255, 255, 255, 0.3);
    }

    .trust-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        flex-shrink: 0;
    }

    .trust-item strong {
        display: block;
        font-size: 15.5px;
        line-height: 1.3;
    }

    .trust-item span {
        font-size: 13px;
        opacity: 0.88;
    }

    /* ---------- SECTION HEADINGS ---------- */

    section {
        padding: 100px 0 0;
    }

    .eyebrow {
        display: inline-block;
        font-size: 12.5px;
        font-weight: 800;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: var(--p600);
        background: var(--p100);
        padding: 6px 14px;
        border-radius: 999px;
        margin-bottom: 14px;
    }

    .section-head h2 {
        font-size: clamp(30px, 3.8vw, 44px);
        line-height: 1.12;
        font-weight: 800;
        letter-spacing: -1px;
        max-width: 640px;
    }

    .section-head p {
        margin-top: 12px;
        color: var(--ink-soft);
        font-size: 17px;
        max-width: 540px;
    }

    .section-head.center {
        text-align: center;
    }

    .section-head.center h2,
    .section-head.center p {
        margin-left: auto;
        margin-right: auto;
    }

    /* ---------- BENTO ---------- */

    .bento {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 20px;
        margin-top: 48px;
    }

    .tile {
        border-radius: var(--radius);
        padding: 32px;
        position: relative;
        overflow: hidden;
        transition: transform 0.25s, box-shadow 0.25s;
    }

    .tile:hover {
        transform: translateY(-5px);
        box-shadow: 0 22px 44px rgba(139, 92, 246, 0.2);
    }

    .tile .ico {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
        background: rgba(255, 255, 255, 0.7);
    }

    .tile h3 {
        font-size: 22px;
        letter-spacing: -0.4px;
        margin-bottom: 8px;
    }

    .tile p {
        font-size: 15px;
        max-width: 420px;
    }

    .tile.t1 {
        grid-column: span 4;
        background: linear-gradient(135deg, var(--p200), var(--orchid));
    }

    .tile.t2 {
        grid-column: span 2;
        background: var(--p600);
        color: #fff;
    }

    .tile.t2 .ico {
        background: rgba(255, 255, 255, 0.22);
    }

    .tile.t3 {
        grid-column: span 2;
        background: var(--p100);
        border: 1px solid var(--p200);
    }

    .tile.t4 {
        grid-column: span 4;
        background: linear-gradient(135deg, var(--p300), var(--p400));
    }

    .tile.t1::after,
    .tile.t4::after {
        content: "";
        position: absolute;
        right: -50px;
        bottom: -60px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.28);
    }

    .tile-tags {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 18px;
        position: relative;
        z-index: 1;
    }

    .tile-tags span {
        background: rgba(255, 255, 255, 0.75);
        color: var(--p700);
        font-size: 12.5px;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 999px;
    }

    /* ---------- LISTINGS ---------- */

    .listing-top {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .home-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 26px;
        margin-top: 44px;
    }

    .home-card {
        background: #fff;
        border: 1px solid var(--p200);
        border-radius: 30px;
        padding: 12px 12px 22px;
        transition: transform 0.25s, box-shadow 0.25s;
    }

    .home-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 26px 50px rgba(139, 92, 246, 0.22);
    }

    .home-photo {
        position: relative;
        height: 230px;
        border-radius: 22px;
        overflow: hidden;
    }

    .home-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .home-card:hover .home-photo img {
        transform: scale(1.07);
    }

    .badge-verified {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(255, 255, 255, 0.92);
        color: var(--p700);
        font-size: 12px;
        font-weight: 800;
        padding: 6px 12px;
        border-radius: 999px;
    }

    .heart {
        position: absolute;
        top: 10px;
        right: 12px;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.92);
        color: var(--p600);
        font-size: 18px;
        cursor: pointer;
        user-select: none;
        transition: transform 0.15s;
    }

    .heart:hover {
        transform: scale(1.12);
    }

    .heart.liked {
        color: #D946EF;
    }

    .price-pill {
        position: absolute;
        left: 12px;
        bottom: 12px;
        background: linear-gradient(135deg, var(--p500), var(--p700));
        color: #fff;
        font-weight: 800;
        font-size: 15px;
        padding: 8px 16px;
        border-radius: 999px;
        box-shadow: 0 8px 18px rgba(114, 67, 224, 0.4);
    }

    .price-pill small {
        font-weight: 600;
        opacity: 0.85;
        font-size: 12px;
    }

    .home-body {
        padding: 18px 12px 0;
    }

    .home-body h3 {
        font-size: 19px;
        letter-spacing: -0.3px;
        margin-bottom: 4px;
    }

    .home-loc {
        font-size: 14px;
        color: var(--ink-soft);
        font-weight: 500;
    }

    .home-tags {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 16px;
    }

    .tag {
        background: var(--p100);
        color: var(--p700);
        font-size: 12.5px;
        font-weight: 700;
        padding: 6px 13px;
        border-radius: 999px;
    }

    .rating {
        font-weight: 800;
        color: var(--p600);
        font-size: 14px;
    }

    .empty-note {
        margin-top: 40px;
        text-align: center;
        padding: 44px 20px;
        border-radius: var(--radius);
        border: 2px dashed var(--p300);
        background: var(--p100);
        color: var(--ink-soft);
        font-weight: 600;
    }

    .empty-note[hidden] {
        display: none;
    }

    .empty-note button {
        margin-top: 14px;
    }

    /* ---------- STEPS ---------- */

    .steps {
        position: relative;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 28px;
        margin-top: 60px;
    }

    .steps::before {
        content: "";
        position: absolute;
        top: 44px;
        left: 16%;
        right: 16%;
        border-top: 3px dashed var(--p300);
        z-index: 0;
    }

    .step {
        position: relative;
        z-index: 1;
        text-align: center;
        padding: 0 12px;
    }

    .step-num {
        width: 88px;
        height: 88px;
        margin: 0 auto 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: 800;
        color: #fff;
        border: 6px solid var(--p50);
        box-shadow: 0 14px 28px rgba(139, 92, 246, 0.3);
    }

    .step:nth-child(1) .step-num {
        background: var(--p400);
    }

    .step:nth-child(2) .step-num {
        background: var(--p600);
    }

    .step:nth-child(3) .step-num {
        background: var(--p700);
    }

    .step h3 {
        font-size: 21px;
        margin-bottom: 8px;
    }

    .step p {
        color: var(--ink-soft);
        font-size: 15.5px;
        max-width: 290px;
        margin: 0 auto;
    }

    /* ---------- LANDLORD CTA ---------- */

    .cta-band {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        align-items: center;
        gap: 30px;
        padding: 54px 56px;
        border-radius: 38px;
        color: #fff;
        background: linear-gradient(120deg, var(--p400) 0%, var(--p600) 55%, var(--mauve) 130%);
        box-shadow: 0 30px 60px rgba(139, 92, 246, 0.35);
    }

    .cta-band::before,
    .cta-band::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.14);
    }

    .cta-band::before {
        width: 300px;
        height: 300px;
        right: -80px;
        top: -120px;
    }

    .cta-band::after {
        width: 200px;
        height: 200px;
        right: 160px;
        bottom: -110px;
    }

    .cta-band h2 {
        font-size: clamp(28px, 3.4vw, 40px);
        line-height: 1.12;
        letter-spacing: -0.8px;
        margin-bottom: 12px;
        position: relative;
        z-index: 1;
    }

    .cta-band p {
        opacity: 0.92;
        font-size: 16.5px;
        max-width: 460px;
        position: relative;
        z-index: 1;
    }

    .cta-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
        position: relative;
        z-index: 1;
    }

    .btn-ghost {
        color: #fff;
        border: 2px solid rgba(255, 255, 255, 0.6);
        background: transparent;
    }

    .btn-ghost:hover {
        background: rgba(255, 255, 255, 0.16);
    }

    /* ---------- FOOTER ---------- */

    footer {
        margin-top: 100px;
        background: linear-gradient(180deg, var(--p200) 0%, var(--p300) 100%);
        padding: 60px 0 34px;
        border-radius: 48px 48px 0 0;
    }

    .footer-grid {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 30px;
        flex-wrap: wrap;
    }

    .footer-brand p {
        margin-top: 12px;
        max-width: 330px;
        font-size: 14.5px;
        color: var(--ink-soft);
    }

    .footer-cols {
        display: flex;
        gap: 56px;
        flex-wrap: wrap;
    }

    .footer-cols h4 {
        font-size: 13px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: var(--p700);
        margin-bottom: 12px;
    }

    .footer-cols a {
        display: block;
        font-size: 14.5px;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: 8px;
    }

    .footer-cols a:hover {
        color: var(--p700);
    }

    .copy {
        margin-top: 40px;
        padding-top: 22px;
        border-top: 1px solid rgba(114, 67, 224, 0.2);
        font-size: 13px;
        color: var(--ink-soft);
        text-align: center;
    }

    /* ---------- RESPONSIVE ---------- */

    @media (max-width: 980px) {
        .hero-grid {
            grid-template-columns: 1fr;
        }

        .collage {
            height: 460px;
            max-width: 520px;
            margin: 0 auto;
            width: 100%;
        }

        .nav-links {
            display: none;
        }

        .trust-bar {
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .trust-item+.trust-item {
            border-left: none;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            padding-top: 18px;
        }

        .bento {
            grid-template-columns: 1fr;
        }

        .tile.t1,
        .tile.t2,
        .tile.t3,
        .tile.t4 {
            grid-column: span 1;
        }

        .home-grid {
            grid-template-columns: 1fr;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .steps {
            grid-template-columns: 1fr;
            gap: 40px;
        }

        .steps::before {
            display: none;
        }

        .cta-band {
            grid-template-columns: 1fr;
            padding: 40px 28px;
        }

        .cta-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 600px) {
        .search-card {
            grid-template-columns: 1fr;
        }

        .search-card .btn {
            padding: 15px;
        }

        .brand-subtitle {
            display: none;
        }

        .nav-buttons .login {
            display: none;
        }

        .collage .round {
            width: 130px;
            height: 130px;
        }

        .sticker {
            width: 84px;
            height: 84px;
            font-size: 10.5px;
        }
    }
    </style>
</head>

<body>

    <!-- =====================================================
     FLOATING NAVIGATION
     ===================================================== -->

    <div class="nav-shell">
        <nav class="home-nav">

            <a href="index.php" class="brand">
                <div class="brand-icon">🏠</div>
                <div>
                    <div class="brand-name">UniMtaa</div>
                    <span class="brand-subtitle">Student Accommodation</span>
                </div>
            </a>

            <div class="nav-links">
                <a href="#homes">Browse homes</a>
                <a href="#how">How it works</a>
                <a href="#why">Why UniMtaa</a>
                <a href="#landlords">For landlords</a>
            </div>

            <div class="nav-buttons">
                <a href="login.php" class="login">Login</a>
                <a href="register.php" class="btn btn-main">Join free</a>
            </div>

        </nav>
    </div>


    <!-- =====================================================
     HERO
     ===================================================== -->

    <header class="hero">

        <div class="blob b1"></div>
        <div class="blob b2"></div>
        <div class="blob b3"></div>

        <div class="wrap hero-grid">

            <div class="hero-copy">

                <div class="hero-tag">✨ Student housing, sorted</div>

                <h1>Find your <em>mtaa</em>.<br>Not just a room.</h1>

                <p class="hero-text">
                    Browse student homes near campus, compare real prices
                    in KSh and see what other students say before you
                    ever pay a deposit.
                </p>

                <form class="search-card" id="searchForm">

                    <div class="field">
                        <label for="loc">Where to?</label>
                        <input type="text" id="loc" placeholder="e.g. Kilimani" autocomplete="off">
                    </div>

                    <div class="field">
                        <label for="budget">Monthly budget</label>
                        <select id="budget">
                            <option value="any">Any budget</option>
                            <option value="low">Under KSh 10,000</option>
                            <option value="mid">KSh 10,000 – 20,000</option>
                            <option value="high">Above KSh 20,000</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-main">Search homes</button>

                </form>

                <div class="quick-chips">
                    <span>Try:</span>
                    <button type="button" class="chip" data-fill="Kilimani">Kilimani</button>
                    <button type="button" class="chip" data-fill="Ruaka">Ruaka</button>
                    <button type="button" class="chip" data-fill="Upper Hill">Upper Hill</button>
                </div>

            </div>


            <div class="collage">

                <div class="sticker">Made for<br>Nairobi<br>students</div>

                <div class="arch">
                    <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1000&q=85"
                        alt="Bright, modern student accommodation">
                </div>

                <div class="round">
                    <img src="https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=600&q=85"
                        alt="Cosy student apartment interior">
                </div>

                <div class="float f1">
                    ✓ Reviewed listing
                    <small>Checked before it goes live</small>
                </div>

                <div class="float f2">
                    ★ 4.8 student rating
                    <small>Campus View Residence</small>
                </div>

            </div>

        </div>

    </header>


    <!-- =====================================================
     TRUST STRIP
     ===================================================== -->

    <div class="trust">
        <div class="wrap">
            <div class="trust-bar reveal">

                <div class="trust-item">
                    <div class="trust-icon">🛡️</div>
                    <div>
                        <strong>Listings get reviewed</strong>
                        <span>Admins check each one first</span>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">💬</div>
                    <div>
                        <strong>Honest student ratings</strong>
                        <span>Hear from people who've lived there</span>
                    </div>
                </div>

                <div class="trust-item">
                    <div class="trust-icon">💸</div>
                    <div>
                        <strong>Prices in plain KSh</strong>
                        <span>Know the rent before you visit</span>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- =====================================================
     WHY UNIMTAA (BENTO)
     ===================================================== -->

    <section id="why">
        <div class="wrap">

            <div class="section-head reveal">
                <span class="eyebrow">Why UniMtaa</span>
                <h2>House hunting shouldn't feel like a second course unit.</h2>
                <p>We took the stress out of the usual WhatsApp-groups-and-guesswork routine.</p>
            </div>

            <div class="bento">

                <div class="tile t1 reveal">
                    <div class="ico">🔎</div>
                    <h3>Search the way you actually live</h3>
                    <p>
                        Filter by location, price and amenities so you only
                        see places that fit your budget and your commute.
                    </p>
                    <div class="tile-tags">
                        <span>📍 Location</span>
                        <span>💰 Price</span>
                        <span>🛋️ Amenities</span>
                    </div>
                </div>

                <div class="tile t2 reveal">
                    <div class="ico">✓</div>
                    <h3>Checked first</h3>
                    <p>
                        Listings are reviewed before they appear, so
                        you can spot the ones that need a closer look.
                    </p>
                </div>

                <div class="tile t3 reveal">
                    <div class="ico">★</div>
                    <h3>Real student reviews</h3>
                    <p>
                        See ratings and experiences from students who
                        have been there, not just the landlord's pitch.
                    </p>
                </div>

                <div class="tile t4 reveal">
                    <div class="ico">⚖️</div>
                    <h3>Compare with confidence</h3>
                    <p>
                        Put prices, amenities and ratings side by side
                        and choose the home that is genuinely worth it.
                    </p>
                </div>

            </div>

        </div>
    </section>


    <!-- =====================================================
     LISTINGS
     ===================================================== -->

    <section id="homes">
        <div class="wrap">

            <div class="listing-top reveal">
                <div class="section-head">
                    <span class="eyebrow">Browse homes</span>
                    <h2>Homes students are eyeing right now.</h2>
                </div>
                <a href="register.php" class="btn btn-soft">See all homes →</a>
            </div>

            <div class="home-grid">

                <!-- HOME 1 -->
                <article class="home-card reveal" data-title="Modern Student Apartment"
                    data-location="Kilimani, Nairobi" data-price="18000">

                    <div class="home-photo">
                        <img src="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=800&q=85"
                            alt="Modern student apartment">
                        <span class="badge-verified">✓ Verified</span>
                        <span class="heart">♡</span>
                        <span class="price-pill">KSh 18,000 <small>/ month</small></span>
                    </div>

                    <div class="home-body">
                        <h3>Modern Student Apartment</h3>
                        <div class="home-loc">📍 Kilimani, Nairobi</div>
                        <div class="home-tags">
                            <span class="tag">🛏 1 Bedroom</span>
                            <span class="rating">★ 4.7</span>
                        </div>
                    </div>

                </article>

                <!-- HOME 2 -->
                <article class="home-card reveal" data-title="The Nest Residence" data-location="Ruaka, Nairobi"
                    data-price="15000">

                    <div class="home-photo">
                        <img src="https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=800&q=85"
                            alt="The Nest Residence interior">
                        <span class="badge-verified">✓ Verified</span>
                        <span class="heart">♡</span>
                        <span class="price-pill">KSh 15,000 <small>/ month</small></span>
                    </div>

                    <div class="home-body">
                        <h3>The Nest Residence</h3>
                        <div class="home-loc">📍 Ruaka, Nairobi</div>
                        <div class="home-tags">
                            <span class="tag">🛏 Studio</span>
                            <span class="rating">★ 4.5</span>
                        </div>
                    </div>

                </article>

                <!-- HOME 3 -->
                <article class="home-card reveal" data-title="Campus View Residence" data-location="Upper Hill, Nairobi"
                    data-price="20000">

                    <div class="home-photo">
                        <img src="https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=85"
                            alt="Campus View Residence">
                        <span class="badge-verified">✓ Verified</span>
                        <span class="heart">♡</span>
                        <span class="price-pill">KSh 20,000 <small>/ month</small></span>
                    </div>

                    <div class="home-body">
                        <h3>Campus View Residence</h3>
                        <div class="home-loc">📍 Upper Hill, Nairobi</div>
                        <div class="home-tags">
                            <span class="tag">🛏 1 Bedroom</span>
                            <span class="rating">★ 4.8</span>
                        </div>
                    </div>

                </article>

            </div>

            <div class="empty-note" id="emptyNote" hidden>
                <div style="font-size:34px;">🔍</div>
                No homes match that search just yet. Try another area or budget.
                <br>
                <button type="button" class="btn btn-soft" id="resetSearch">Show all homes</button>
            </div>

        </div>
    </section>


    <!-- =====================================================
     HOW IT WORKS
     ===================================================== -->

    <section id="how">
        <div class="wrap">

            <div class="section-head center reveal">
                <span class="eyebrow">How it works</span>
                <h2>From “where do I live?” to keys in hand.</h2>
                <p>Three easy steps, no endless scrolling.</p>
            </div>

            <div class="steps">

                <div class="step reveal">
                    <div class="step-num">1</div>
                    <h3>Search</h3>
                    <p>Tell us where you want to stay and what you can afford each month.</p>
                </div>

                <div class="step reveal">
                    <div class="step-num">2</div>
                    <h3>Compare</h3>
                    <p>Line up prices, amenities and ratings until a clear favourite emerges.</p>
                </div>

                <div class="step reveal">
                    <div class="step-num">3</div>
                    <h3>Choose with confidence</h3>
                    <p>Lean on listing checks and student reviews to make a decision you won't regret.</p>
                </div>

            </div>

        </div>
    </section>


    <!-- =====================================================
     LANDLORD CTA
     ===================================================== -->

    <section id="landlords">
        <div class="wrap">

            <div class="cta-band reveal">

                <div>
                    <h2>Got a room to rent? Meet students who need one.</h2>
                    <p>
                        Create a free landlord account, list your
                        accommodation and let students find you.
                    </p>
                </div>

                <div class="cta-actions">
                    <a href="register.php" class="btn btn-white">List your place →</a>
                    <a href="login.php" class="btn btn-ghost">I have an account</a>
                </div>

            </div>

        </div>
    </section>


    <!-- =====================================================
     FOOTER
     ===================================================== -->

    <footer>
        <div class="wrap">

            <div class="footer-grid">

                <div class="footer-brand">
                    <a href="index.php" class="brand">
                        <div class="brand-icon">🏠</div>
                        <div class="brand-name">UniMtaa</div>
                    </a>
                    <p>
                        A web accommodation discovery and rating
                        system for university students.
                    </p>
                </div>

                <div class="footer-cols">

                    <div>
                        <h4>Explore</h4>
                        <a href="#homes">Browse homes</a>
                        <a href="#how">How it works</a>
                        <a href="#why">Why UniMtaa</a>
                    </div>

                    <div>
                        <h4>Account</h4>
                        <a href="login.php">Login</a>
                        <a href="register.php" role="button">Register</a>
                        <a href="#landlords">For landlords</a>
                    </div>

                </div>

            </div>

            <div class="copy">© 2026 UniMtaa. All rights reserved.</div>

        </div>
    </footer>


    <script>
    // ---------- Search: filters the sample homes on this page ----------
    var form = document.getElementById("searchForm");
    var locInput = document.getElementById("loc");
    var budget = document.getElementById("budget");
    var cards = document.querySelectorAll(".home-card");
    var emptyNote = document.getElementById("emptyNote");

    function applyFilter() {
        var q = locInput.value.trim().toLowerCase();
        var b = budget.value;
        var shown = 0;

        cards.forEach(function(card) {
            var text = (card.dataset.location + " " + card.dataset.title).toLowerCase();
            var price = Number(card.dataset.price);
            var ok = !q || text.indexOf(q) !== -1;

            if (b === "low") {
                ok = ok && price < 10000;
            }
            if (b === "mid") {
                ok = ok && price >= 10000 && price <= 20000;
            }
            if (b === "high") {
                ok = ok && price > 20000;
            }

            card.style.display = ok ? "" : "none";
            if (ok) {
                shown++;
            }
        });

        emptyNote.hidden = shown !== 0;
    }

    form.addEventListener("submit", function(e) {
        e.preventDefault();
        applyFilter();
        document.getElementById("homes").scrollIntoView({
            behavior: "smooth"
        });
    });

    document.querySelectorAll(".chip").forEach(function(chip) {
        chip.addEventListener("click", function() {
            locInput.value = chip.dataset.fill;
            applyFilter();
            document.getElementById("homes").scrollIntoView({
                behavior: "smooth"
            });
        });
    });

    document.getElementById("resetSearch").addEventListener("click", function() {
        locInput.value = "";
        budget.value = "any";
        applyFilter();
    });

    // ---------- Heart toggle ----------
    document.querySelectorAll(".heart").forEach(function(heart) {
        heart.addEventListener("click", function() {
            var liked = heart.classList.toggle("liked");
            heart.textContent = liked ? "♥" : "♡";
        });
    });

    // ---------- Reveal on scroll ----------
    var revealEls = document.querySelectorAll(".reveal");

    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("in");
                    io.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12
        });

        revealEls.forEach(function(el) {
            io.observe(el);
        });
    } else {
        revealEls.forEach(function(el) {
            el.classList.add("in");
        });
    }
    </script>

</body>

</html>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --nv-red:      #e8002d;
            --nv-red-dark: #b80024;
            --nv-ink:      #0d0d0d;
            --nv-border:   #e5e7eb;
            --nv-muted:    #6b7280;
            --radius:      12px;
            --transition:  all 0.28s cubic-bezier(0.4,0,0.2,1);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        @media(max-width:991px){
            body { grid-template-columns: 1fr; }
            .brand-panel { display: none !important; }
        }

        /* ===== BRAND PANEL ===== */
        .brand-panel {
            background: var(--nv-ink);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 48px;
            min-height: 100vh;
        }
        .brand-panel-bg {
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 60% 80%, rgba(232,0,45,0.25) 0%, transparent 70%),
                radial-gradient(ellipse 50% 40% at 20% 20%, rgba(232,0,45,0.1) 0%, transparent 60%);
        }
        .brand-panel-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 36px 36px;
        }
        .brand-logo {
            font-family: 'Poppins', sans-serif;
            font-size: 24px; font-weight: 800;
            color: #fff; text-decoration: none;
            letter-spacing: -0.04em;
            position: relative; z-index: 2;
        }
        .brand-logo span { color: var(--nv-red); }
        .brand-content {
            position: relative; z-index: 2;
            margin-top: 80px;
        }
        .brand-content h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(36px, 4vw, 52px);
            font-weight: 800; color: #fff;
            letter-spacing: -0.03em;
            line-height: 1.1; margin-bottom: 16px;
        }
        .brand-content p {
            color: rgba(255,255,255,0.65);
            font-size: 16px; line-height: 1.7;
            max-width: 340px;
        }
        .brand-bottom {
            position: relative; z-index: 2;
            margin-top: auto;
        }
        .brand-track-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,0.5); font-size: 13px;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50px; padding: 10px 18px;
            transition: var(--transition);
        }
        .brand-track-link:hover {
            color: var(--nv-red);
            border-color: rgba(232,0,45,0.4);
        }

        /* ===== FORM PANEL ===== */
        .form-panel {
            display: flex; align-items: center;
            justify-content: center;
            padding: 48px 24px;
            min-height: 100vh;
            overflow-y: auto;
        }
        .form-inner { width: 100%; max-width: 440px; }

        /* ===== STEPPER ===== */
        .stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 40px;
            list-style: none;
            padding: 0;
        }
        .stepper-item {
            flex: 1; display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .stepper-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px; left: 50%;
            width: 100%; height: 2px;
            background: var(--nv-border);
            z-index: 0;
            transition: background 0.4s;
        }
        .stepper-item.done:not(:last-child)::after {
            background: var(--nv-red);
        }
        .step-circle {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            font-family: 'Poppins', sans-serif;
            position: relative; z-index: 1;
            border: 2px solid var(--nv-border);
            background: #fff; color: var(--nv-muted);
            transition: var(--transition);
        }
        .step-circle.active {
            background: var(--nv-red);
            border-color: var(--nv-red);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(232,0,45,0.15);
        }
        .step-circle.done {
            background: var(--nv-red);
            border-color: var(--nv-red);
            color: #fff;
        }
        .step-label {
            margin-top: 8px; font-size: 11px;
            color: var(--nv-muted); text-align: center;
            max-width: 72px; line-height: 1.3;
        }
        .step-label.active { color: var(--nv-ink); font-weight: 700; }
        .step-label.done   { color: var(--nv-red); font-weight: 600; }

        /* ===== TYPOGRAPHY ===== */
        .form-inner h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 26px; font-weight: 800;
            color: var(--nv-ink); letter-spacing: -0.02em;
            margin-bottom: 6px;
        }
        .subtitle {
            color: var(--nv-muted); font-size: 14px;
            margin-bottom: 32px;
        }
        .subtitle a { color: var(--nv-red); font-weight: 600; text-decoration: none; }
        .subtitle a:hover { text-decoration: underline; }

        /* ===== FIELDS ===== */
        .nv-field-group { margin-bottom: 22px; }
        .nv-field-group label {
            display: block; font-size: 11px;
            color: var(--nv-muted); font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.07em;
            margin-bottom: 4px;
        }
        .nv-field {
            width: 100%; border: none;
            border-bottom: 2px solid var(--nv-border);
            padding: 10px 0; font-size: 15px;
            color: var(--nv-ink); background: transparent;
            outline: none; font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }
        .nv-field:focus { border-bottom-color: var(--nv-red); }
        .nv-field-wrap { position: relative; }
        .nv-field-wrap .nv-field { padding-right: 36px; }

        /* ===== PHONE PREFIX ===== */
        .nv-prefix-wrap {
            display: flex; align-items: center;
            border-bottom: 2px solid var(--nv-border);
            transition: border-color 0.2s;
        }
        .nv-prefix-wrap:focus-within { border-bottom-color: var(--nv-red); }
        .nv-field-prefix {
            color: var(--nv-muted); font-size: 15px; font-weight: 600;
            margin-right: 6px; user-select: none;
        }
        .nv-prefix-wrap .nv-field {
            border-bottom: none; padding-left: 0; padding-right: 0;
            flex: 1;
        }
        .nv-prefix-wrap .nv-field:focus { border-bottom-color: transparent; }

        .toggle-pw {
            position: absolute; right: 0; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--nv-muted); cursor: pointer;
            font-size: 18px; padding: 4px;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: var(--nv-ink); }

        .nv-select {
            width: 100%; border: none;
            border-bottom: 2px solid var(--nv-border);
            padding: 10px 0; font-size: 15px;
            color: var(--nv-ink); background: transparent;
            outline: none; appearance: none;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s; cursor: pointer;
        }
        .nv-select:focus { border-bottom-color: var(--nv-red); }
        .nv-select-wrap { position: relative; }
        .nv-select-icon {
            position: absolute; right: 0; top: 50%;
            transform: translateY(-50%);
            color: var(--nv-muted); font-size: 14px;
            pointer-events: none;
        }

        /* ===== ALERTS ===== */
        .nv-error {
            background: rgba(232,0,45,0.07);
            border: 1px solid rgba(232,0,45,0.2);
            border-radius: var(--radius);
            padding: 12px 16px; color: var(--nv-red);
            font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 20px;
        }
        .nv-info {
            background: rgba(37,99,235,0.06);
            border: 1px solid rgba(37,99,235,0.18);
            border-radius: var(--radius);
            padding: 14px 16px; color: #1d4ed8;
            font-size: 14px;
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 20px;
        }

        /* ===== BUTTONS ===== */
        .btn-register {
            background: var(--nv-ink); color: #fff;
            border: none; border-radius: 50px;
            padding: 12px 28px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700; font-size: 15px;
            cursor: pointer; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-register:hover {
            background: var(--nv-red);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(232,0,45,0.3);
        }
        .back-link {
            color: var(--nv-muted); font-size: 13px;
            font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--nv-red); }

        /* ===== OTP BOXES ===== */
        .otp-group {
            display: flex; gap: 12px;
            justify-content: center;
            margin: 28px 0;
        }
        .otp-box {
            width: 52px; height: 60px;
            border: 2px solid var(--nv-border);
            border-radius: 10px;
            font-size: 24px; font-weight: 700;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            color: var(--nv-ink);
            outline: none;
            transition: var(--transition);
            background: #fafafa;
        }
        .otp-box:focus {
            border-color: var(--nv-red);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(232,0,45,0.12);
        }
        .otp-box.filled {
            border-color: var(--nv-red);
            background: rgba(232,0,45,0.04);
        }

        /* ===== OTP DEMO BOX ===== */
        .otp-demo-box {
            background: linear-gradient(135deg, #0d0d0d, #1a1a1a);
            border: 1px solid rgba(232,0,45,0.3);
            border-radius: var(--radius);
            padding: 16px 20px;
            text-align: center;
            margin-bottom: 24px;
        }
        .otp-demo-box .label {
            color: rgba(255,255,255,0.5);
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em;
            margin-bottom: 6px;
        }
        .otp-demo-box .code {
            font-family: 'Poppins', sans-serif;
            font-size: 32px; font-weight: 800;
            color: #fff; letter-spacing: 0.18em;
        }
        .otp-demo-box .code span { color: var(--nv-red); }

        /* ===== PASSWORD STRENGTH ===== */
        .strength-bar {
            height: 4px; border-radius: 2px;
            background: var(--nv-border);
            margin-top: 8px; overflow: hidden;
        }
        .strength-fill {
            height: 100%; border-radius: 2px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        }
        .strength-text {
            font-size: 11px; margin-top: 5px;
            font-weight: 600; height: 16px;
        }

        /* ===== PROFILE SUMMARY CARD (step 4) ===== */
        .profile-card {
            background: #fafafa;
            border: 1px solid var(--nv-border);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 28px;
            display: flex; align-items: center; gap: 14px;
        }
        .profile-avatar {
            width: 52px; height: 52px;
            background: var(--nv-red);
            border-radius: 50%;
            display: flex; align-items: center;
            justify-content: center;
            color: #fff; font-size: 22px;
            flex-shrink: 0;
        }
        .profile-card h5 {
            font-size: 15px; font-weight: 700;
            color: var(--nv-ink); margin: 0 0 2px;
        }
        .profile-card p {
            font-size: 13px; color: var(--nv-muted); margin: 0;
        }

        /* ===== LAYOUT HELPERS ===== */
        .row-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media(max-width:480px){ .row-fields { grid-template-columns: 1fr; } }
    </style>
<?php
// Stepper helper function used by all steps
function stepperHtml(int $active): void {
    $steps = ['Sign up','Verify','Set password','Complete profile'];
    echo '<ol class="stepper">';
    foreach($steps as $i => $label){
        $n    = $i + 1;
        $cls  = $n < $active ? 'done' : ($n == $active ? 'active' : '');
        $icon = $n < $active ? '<i class="bi bi-check2"></i>' : $n;
        echo "<li class='stepper-item $cls'>
                <div class='step-circle $cls'>$icon</div>
                <span class='step-label $cls'>$label</span>
              </li>";
    }
    echo '</ol>';
}
?>

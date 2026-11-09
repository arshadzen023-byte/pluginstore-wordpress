
<?php
defined('ABSPATH') || exit;

if (is_user_logged_in()) {
    echo '<p>You are already logged in.</p>';
    return;
}

$ark_otp_nonce = wp_create_nonce('ark_otp_login');
?>

<div class="ark-otp-page">
    <div class="ark-otp-card">
        <div class="ark-otp-logo">
            <span class="material-symbols-rounded">shopping_bag</span>
        </div>

        <h2>Welcome to ARK Mart</h2>
        <p class="ark-otp-subtitle">Login or create your account using your mobile number</p>

       
<form id="ark-otp-phone-form">
    <label for="ark-otp-email">Email Address</label>

    <input
        id="ark-otp-email"
        name="email"
        type="email"
        autocomplete="email"
        placeholder="Enter your email address"
        required
        style="width:100%;box-sizing:border-box;min-height:52px;padding:12px;border:1px solid #ded9e8;border-radius:12px;margin-bottom:18px;"
    >

    <label for="ark-otp-phone">Mobile Number</label>


            <div class="ark-otp-phone-field">
                <span class="ark-otp-country">🇮🇳 +91</span>
                <input
                    id="ark-otp-phone"
                    name="phone"
                    type="tel"
                    inputmode="numeric"
                    autocomplete="tel-national"
                    maxlength="10"
                    pattern="[6-9][0-9]{9}"
                    placeholder="Enter 10-digit mobile number"
                    required
                >
            </div>

            <button type="submit" id="ark-otp-send" class="ark-otp-button">
                Send OTP
            </button>
        </form>

        <form id="ark-otp-verify-form" hidden>
            <label for="ark-otp-code">Enter 6-digit OTP</label>

            <input
                id="ark-otp-code"
                name="otp"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                placeholder="Enter OTP"
                required
            >

            <button type="submit" id="ark-otp-verify" class="ark-otp-button">
                Verify &amp; Login
            </button>

            <button type="button" id="ark-otp-change" class="ark-otp-secondary">
                Change mobile number
            </button>
        </form>

        <div id="ark-otp-message" class="ark-otp-message" role="status" aria-live="polite"></div>

        <p class="ark-otp-terms">
            By continuing, you agree to our website terms and privacy policy.
        </p>
    </div>
</div>

<style>
.ark-otp-page {
    width: 100%;
    box-sizing: border-box;
    padding: 36px 16px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.ark-otp-card {
    width: 100%;
    max-width: 430px;
    box-sizing: border-box;
    padding: 30px;
    border: 1px solid #eee8f7;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 12px 38px rgba(45, 30, 75, .08);
}
.ark-otp-logo {
    width: 58px;
    height: 58px;
    margin: 0 auto 18px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eee7ff;
    color: #6741b8;
}
.ark-otp-logo .material-symbols-rounded {
    font-size: 32px;
}
.ark-otp-card h2 {
    margin: 0 0 8px;
    text-align: center;
    font-size: 25px;
    line-height: 1.3;
}
.ark-otp-subtitle {
    margin: 0 0 26px;
    text-align: center;
    color: #77717f;
    font-size: 14px;
    line-height: 1.6;
}
.ark-otp-card label {
    display: block;
    margin: 0 0 9px;
    font-size: 14px;
    font-weight: 600;
}
.ark-otp-phone-field {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 52px;
    padding: 0 12px;
    border: 1px solid #ded9e8;
    border-radius: 12px;
    box-sizing: border-box;
}
.ark-otp-country {
    white-space: nowrap;
    font-weight: 600;
    font-size: 14px;
}
.ark-otp-phone-field input {
    min-width: 0;
    width: 100%;
    border: 0 !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 12px 0 !important;
    background: transparent !important;
}
#ark-otp-code {
    width: 100%;
    min-height: 52px;
    box-sizing: border-box;
    margin-bottom: 14px;
    padding: 12px;
    border: 1px solid #ded9e8;
    border-radius: 12px;
    font-size: 20px;
    letter-spacing: 6px;
    text-align: center;
}
.ark-otp-button,
.ark-otp-secondary {
    width: 100%;
    min-height: 50px;
    margin-top: 16px;
    border: 0;
    border-radius: 12px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 700;
}
.ark-otp-button {
    background: #6842b8;
    color: #fff;
}
.ark-otp-button:hover {
    background: #523195;
}
.ark-otp-button:disabled {
    opacity: .65;
    cursor: wait;
}
.ark-otp-secondary {
    margin-top: 8px;
    background: #f4f0fb;
    color: #5b399b;
}
.ark-otp-message {
    margin-top: 16px;
    font-size: 14px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}
.ark-otp-message:empty {
    display: none;
}
.ark-otp-message.is-error {
    color: #b42318;
}
.ark-otp-message.is-success {
    color: #18794e;
}
.ark-otp-terms {
    margin: 24px 0 0;
    color: #88818f;
    text-align: center;
    font-size: 12px;
    line-height: 1.6;
}
@media (max-width: 480px) {
    .ark-otp-page {
        padding: 22px 12px;
    }
    .ark-otp-card {
        padding: 23px 18px;
        border-radius: 18px;
    }
    .ark-otp-card h2 {
        font-size: 22px;
    }
}
</style>

<script>
(function () {
    const phoneForm = document.getElementById('ark-otp-phone-form');
    const verifyForm = document.getElementById('ark-otp-verify-form');

    if (!phoneForm || !verifyForm) return;

    const phoneInput = document.getElementById('ark-otp-phone');
    const emailInput = document.getElementById('ark-otp-email');
    const otpInput = document.getElementById('ark-otp-code');
    const sendButton = document.getElementById('ark-otp-send');
    const verifyButton = document.getElementById('ark-otp-verify');
    const changeButton = document.getElementById('ark-otp-change');
    const message = document.getElementById('ark-otp-message');

    const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    const nonce = <?php echo wp_json_encode($ark_otp_nonce); ?>;

    let currentPhone = '';

    function showMessage(text, type) {
        message.textContent = text || '';
        message.className = 'ark-otp-message' + (type ? ' is-' + type : '');
    }

    async function sendRequest(action, fields) {
        const body = new URLSearchParams({
            action: action,
            nonce: nonce,
            ...fields
        });

        const response = await fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        });

        const result = await response.json();

        if (!result || !result.success) {
            const errorMessage = result && result.data && result.data.message
                ? result.data.message
                : 'Something went wrong. Please try again.';
            throw new Error(errorMessage);
        }

        return result.data;
    }

    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });

    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });

    phoneForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const phone = phoneInput.value.trim();

        if (!/^[6-9]\d{9}$/.test(phone)) {
            showMessage('Please enter a valid 10-digit Indian mobile number.', 'error');
            return;
        }

        sendButton.disabled = true;
        sendButton.textContent = 'Sending OTP...';
        showMessage('', '');

        try {
            const result = await sendRequest('ark_send_otp', {
                phone: phone
            });

            currentPhone = phone;
            phoneForm.hidden = true;
            verifyForm.hidden = false;
            otpInput.value = '';
            otpInput.focus();

            showMessage(result.message || 'OTP request sent. Please check your phone.', 'success');
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            sendButton.disabled = false;
            sendButton.textContent = 'Send OTP';
        }
    });

    verifyForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const otp = otpInput.value.trim();

        if (!/^\d{6}$/.test(otp)) {
            showMessage('Please enter the 6-digit OTP.', 'error');
            return;
        }

        verifyButton.disabled = true;
        verifyButton.textContent = 'Verifying...';
        showMessage('', '');

        try {
            const result = await sendRequest('ark_verify_otp', {
    phone: currentPhone,
    email: emailInput.value.trim(),
    otp: otp
});

            showMessage(result.message || 'Login successful. Redirecting...', 'success');

            window.location.href = result.redirect || window.location.href;
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            verifyButton.disabled = false;
            verifyButton.textContent = 'Verify & Login';
        }
    });

    changeButton.addEventListener('click', function () {
        verifyForm.hidden = true;
        phoneForm.hidden = false;
        otpInput.value = '';
        showMessage('', '');
        phoneInput.focus();
    });
})();
</script>
<?php
do_action('woocommerce_after_customer_login_form');
?>

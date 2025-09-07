// SVNTeX 2.0 Customer Registration Frontend JS
// Features: Real-time validation, animated OTP, 2FA workflow, modular code, accessibility, JSON backend interaction

document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('registrationForm');
	const mobile = document.getElementById('mobile');
	const email = document.getElementById('email');
	const fname = document.getElementById('fname');
	const lname = document.getElementById('lname');
	const referral = document.getElementById('referral');
	const employee = document.getElementById('employee');
	const password = document.getElementById('password');
	const confirm = document.getElementById('confirm');
	const otp = document.getElementById('otp');
	const sendOtpBtn = document.getElementById('sendOtpBtn');
	const errorsDiv = document.getElementById('formErrors');

	let otpTimer = null;
	let otpCountdown = 60;
	let canResendOtp = true;

	// Utility: Show error message with animation
	function showError(msg) {
		errorsDiv.innerHTML = `<div class='error-msg' role='alert'>${msg}</div>`;
		errorsDiv.classList.add('show');
		setTimeout(() => errorsDiv.classList.remove('show'), 4000);
	}

	// Utility: Show success message
	function showSuccess(msg) {
		errorsDiv.innerHTML = `<div class='success-msg' role='status'>${msg}</div>`;
		errorsDiv.classList.add('success');
		setTimeout(() => errorsDiv.classList.remove('success'), 4000);
	}

	// Real-time validation rules
	function validateMobile() {
		const val = mobile.value.replace(/\D/g, '');
		if (val.length < 10) {
			mobile.setAttribute('aria-invalid', 'true');
			mobile.classList.add('input-error');
			showError('Enter a valid mobile number.');
			return false;
		}
		mobile.setAttribute('aria-invalid', 'false');
		mobile.classList.remove('input-error');
		return true;
	}
	function validateEmail() {
		const val = email.value;
		const valid = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(val);
		if (!valid) {
			email.setAttribute('aria-invalid', 'true');
			email.classList.add('input-error');
			showError('Enter a valid email address.');
			return false;
		}
		email.setAttribute('aria-invalid', 'false');
		email.classList.remove('input-error');
		return true;
	}
	function validatePassword() {
		if (password.value.length < 8) {
			password.setAttribute('aria-invalid', 'true');
			password.classList.add('input-error');
			showError('Password must be at least 8 characters.');
			return false;
		}
		password.setAttribute('aria-invalid', 'false');
		password.classList.remove('input-error');
		return true;
	}
	function validateConfirm() {
		if (password.value !== confirm.value) {
			confirm.setAttribute('aria-invalid', 'true');
			confirm.classList.add('input-error');
			showError('Passwords do not match.');
			return false;
		}
		confirm.setAttribute('aria-invalid', 'false');
		confirm.classList.remove('input-error');
		return true;
	}
	// Optional fields: referral, employee (basic validation)
	function validateOptional(input) {
		if (input.value && !/^\w{3,}$/.test(input.value)) {
			input.setAttribute('aria-invalid', 'true');
			input.classList.add('input-error');
			showError('Invalid code format.');
			return false;
		}
		input.setAttribute('aria-invalid', 'false');
		input.classList.remove('input-error');
		return true;
	}

	// Animated OTP input: auto-focus, digit transitions
	otp.addEventListener('input', function(e) {
		otp.value = otp.value.replace(/\D/g, '').slice(0, 6);
		if (otp.value.length === 6) {
			otp.classList.add('otp-complete');
		} else {
			otp.classList.remove('otp-complete');
		}
	});

	// Send OTP workflow
	sendOtpBtn.addEventListener('click', function() {
		if (!validateMobile()) return;
		if (!canResendOtp) {
			showError('Please wait before resending OTP.');
			return;
		}
		// Simulate AJAX OTP send
		fetch('customer-registration.php', {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded'},
			body: `action=send_otp&mobile=${encodeURIComponent(mobile.value)}`
		})
		.then(res => res.json())
		.then(data => {
			if (data.success) {
				showSuccess('OTP sent!');
				startOtpTimer();
			} else {
				showError(data.error || 'OTP send failed.');
			}
		});
	});

	// OTP timer and resend logic
	function startOtpTimer() {
		canResendOtp = false;
		otpCountdown = 60;
		sendOtpBtn.disabled = true;
		sendOtpBtn.textContent = `Resend OTP (${otpCountdown}s)`;
		otpTimer = setInterval(() => {
			otpCountdown--;
			sendOtpBtn.textContent = `Resend OTP (${otpCountdown}s)`;
			if (otpCountdown <= 0) {
				clearInterval(otpTimer);
				sendOtpBtn.disabled = false;
				sendOtpBtn.textContent = 'Send OTP';
				canResendOtp = true;
			}
		}, 1000);
	}

	// Form field event listeners for real-time validation
	mobile.addEventListener('blur', validateMobile);
	email.addEventListener('blur', validateEmail);
	password.addEventListener('blur', validatePassword);
	confirm.addEventListener('blur', validateConfirm);
	referral.addEventListener('blur', () => validateOptional(referral));
	employee.addEventListener('blur', () => validateOptional(employee));

	// Form submit handler
	form.addEventListener('submit', function(e) {
		e.preventDefault();
		errorsDiv.innerHTML = '';
		// Validate all fields before submit
		if (!validateMobile() || !validateEmail() || !validatePassword() || !validateConfirm() || !validateOptional(referral) || !validateOptional(employee)) {
			showError('Please fix errors before submitting.');
			return;
		}
		if (otp.value.length !== 6) {
			showError('Enter the 6-digit OTP.');
			otp.focus();
			return;
		}
		// Prepare form data
		const formData = new FormData(form);
		formData.append('action', 'register');
		// AJAX submit
		fetch('customer-registration.php', {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(data => {
			if (data.success) {
				showSuccess(`Registration successful! Your Customer ID: ${data.customer_id}`);
				form.reset();
			} else {
				showError(data.errors ? data.errors.join('<br>') : 'Registration failed.');
			}
		});
	});

	// Accessibility: ARIA attributes, keyboard navigation
	form.setAttribute('aria-label', 'Customer Registration Form');
	Array.from(form.elements).forEach(el => {
		el.setAttribute('tabindex', '0');
	});
});

// Comments:
// - All validation and error feedback is animated for better UX.
// - OTP workflow includes timer and resend limit for security.
// - Modular event handlers for maintainability.
// - JSON-based backend interaction for seamless integration.
// - ARIA attributes and tabindex for accessibility.

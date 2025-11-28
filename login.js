const loginTab = document.getElementById('login-tab');
const signupTab = document.getElementById('signup-tab');
const loginForm = document.getElementById('login-form');
const signupForm = document.getElementById('signup-form');
const switchToSignup = document.getElementById('switch-to-signup');
const switchToLogin = document.getElementById('switch-to-login');

function showLogin() {
  loginForm.classList.add('active');
  signupForm.classList.remove('active');
  loginTab.classList.add('active');
  signupTab.classList.remove('active');
}

function showSignup() {
  signupForm.classList.add('active');
  loginForm.classList.remove('active');
  signupTab.classList.add('active');
  loginTab.classList.remove('active');
}

loginTab.addEventListener('click', showLogin);
signupTab.addEventListener('click', showSignup);
switchToSignup.addEventListener('click', (e) => { e.preventDefault(); showSignup(); });
switchToLogin.addEventListener('click', (e) => { e.preventDefault(); showLogin(); });

// Handle login via API
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const emailEl = document.getElementById('login-email');
  const passwordEl = document.getElementById('login-password');
  const errorEl = document.getElementById('login-error');
  errorEl.style.display = 'none';
  errorEl.textContent = '';
  const email = emailEl.value.trim();
  const password = passwordEl.value;

  // client-side validation
  const clientErrors = [];
  if (!email) clientErrors.push('Email is required');
  else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) clientErrors.push('Enter a valid email');
  if (!password) clientErrors.push('Password is required');
  if (clientErrors.length) {
    errorEl.textContent = clientErrors.join('. ');
    errorEl.style.display = 'block';
    return;
  }

  try {
    const res = await fetch('api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ email, password })
    });
    const data = await res.json();
    if (!res.ok) {
      // server may return structured errors
      if (data && data.errors) {
        errorEl.textContent = Object.values(data.errors).join('. ');
      } else if (data && data.error) {
        errorEl.textContent = data.error;
      } else {
        errorEl.textContent = 'Login failed';
      }
      errorEl.style.display = 'block';
      return;
    }
    // success
    window.location.href = 'account.html';
  } catch (err) {
    errorEl.textContent = err.message || 'Network error';
    errorEl.style.display = 'block';
  }
});

// Handle signup via API
signupForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const nameEl = document.getElementById('fullname');
  const ageEl = document.getElementById('age');
  const emailEl = document.getElementById('signup-email');
  const passwordEl = document.getElementById('signup-password');
  const confirmEl = document.getElementById('confirm-password');
  const errorEl = document.getElementById('signup-error');
  errorEl.style.display = 'none';
  errorEl.textContent = '';

  const name = nameEl.value.trim();
  const age = ageEl.value.trim();
  const email = emailEl.value.trim();
  const password = passwordEl.value;
  const confirm = confirmEl.value;

  // client-side validation
  const clientErrors = [];
  if (!name) clientErrors.push('Name is required');
  if (!email) clientErrors.push('Email is required');
  else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) clientErrors.push('Enter a valid email');
  if (!password || password.length < 6) clientErrors.push('Password required (min 6 chars)');
  if (password !== confirm) clientErrors.push('Passwords do not match');
  if (age && (!/^[0-9]+$/.test(age) || parseInt(age,10) < 0)) clientErrors.push('Age must be a non-negative integer');
  if (clientErrors.length) {
    errorEl.textContent = clientErrors.join('. ');
    errorEl.style.display = 'block';
    return;
  }

  try {
    const res = await fetch('api/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, age, email, password })
    });
    const data = await res.json();
    if (!res.ok) {
      if (data && data.errors) {
        errorEl.textContent = Object.values(data.errors).join('. ');
      } else if (data && data.error) {
        errorEl.textContent = data.error;
      } else {
        errorEl.textContent = 'Registration failed';
      }
      errorEl.style.display = 'block';
      return;
    }
    // auto-login after register
    const loginRes = await fetch('api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ email, password })
    });
    if (!loginRes.ok) {
      alert('Registered but auto-login failed. Please login manually.');
      showLogin();
      return;
    }
    window.location.href = 'account.html';
  } catch (err) {
    errorEl.textContent = err.message || 'Network error';
    errorEl.style.display = 'block';
  }
});

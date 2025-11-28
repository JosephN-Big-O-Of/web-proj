// account.js - fetches profile from API and updates the page
async function loadProfile() {
  try {
    const res = await fetch('api/me.php', { credentials: 'include' });
    if (res.status === 401) { window.location.href = 'login.html'; return; }
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Failed to load profile');
    const user = data.user;
    document.getElementById('user-name').textContent = user.name;
    document.getElementById('user-email').textContent = user.email;
    document.getElementById('user-age').textContent = user.age ?? '';
    document.getElementById('user-joined').textContent = new Date(user.joined_at).toLocaleDateString();
  } catch (err) {
    console.error(err);
    alert('Unable to load profile. Please login.');
    window.location.href = 'login.html';
  }
}

document.addEventListener('DOMContentLoaded', loadProfile);

// logout button
const logoutBtn = document.getElementById('logout-btn') || null;
if (logoutBtn) logoutBtn.addEventListener('click', async () => {
  await fetch('api/logout.php', { method: 'POST', credentials: 'include' });
  window.location.href = 'login.html';
});

<?php $page='Register'; include __DIR__.'/includes/header.php'; ?>
<section class="container narrow">
  <div class="auth-grid stack">
    <div class="auth-copy">
      <h1>Create your account</h1>
      <p class="muted">Join CareFlow to manage appointments and access your health records.</p>
    </div>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-error"><?php echo nl2br(htmlspecialchars($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <form class="card" method="post" action="/capstone/auth/register.php">
      <div class="form-field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" required />
      </div>
      <div class="form-field">
        <label for="role">Role</label>
        <select id="role" name="role" required>
          <option value="" disabled selected>Select role</option>
          <option value="doctor">Doctor</option>
          <option value="nurse">Nurse</option>
          <option value="supervisor">Supervisor</option>
          <option value="pharmacist">Pharmacist</option>
          <option value="lab_staff">Lab Staff</option>
        </select>
      </div>
      <div class="form-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required />
      </div>
      <div class="form-field">
        <label for="password">Password</label>
        <div class="password-field" style="position:relative;display:flex;align-items:center;">
          <input type="password" id="password" name="password" minlength="6" required style="padding-right:42px;" />
          <img src="/capstone/assets/img/eye.png" alt="Toggle password visibility" data-target="password" class="toggle-password" style="position:absolute;right:12px;width:22px;height:22px;cursor:pointer;opacity:.7;" />
        </div>
      </div>
      <div class="form-field">
        <label for="confirm_password">Confirm password</label>
        <div class="password-field" style="position:relative;display:flex;align-items:center;">
          <input type="password" id="confirm_password" name="confirm_password" minlength="6" required style="padding-right:42px;" />
          <img src="/capstone/assets/img/eye.png" alt="Toggle password visibility" data-target="confirm_password" class="toggle-password" style="position:absolute;right:12px;width:22px;height:22px;cursor:pointer;opacity:.7;" />
        </div>
      </div>
      <button class="btn btn-primary center" type="submit">Create account</button>
    </form>
  </div>
</section>
<script>
(function(){
  var toggles=document.querySelectorAll('.toggle-password');
  toggles.forEach(function(t){
    t.addEventListener('click',function(){
      var id=t.getAttribute('data-target');
      var input=document.getElementById(id);
      if(!input) return;
      var isPwd=input.type==='password';
      input.type=isPwd?'text':'password';
      t.src=isPwd?'/capstone/assets/img/hidden.png':'/capstone/assets/img/eye.png';
    });
  });
})();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>

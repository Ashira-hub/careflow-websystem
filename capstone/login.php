<?php $page='Login'; include __DIR__.'/includes/header.php'; ?>
<section class="container narrow">
  <div class="auth-grid stack">
    <div class="auth-copy">
      <h1>Welcome back</h1>
      <p class="muted">Sign in to access your dashboard.</p>
    </div>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-error"><?php echo nl2br(htmlspecialchars($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <form class="card" method="post" action="/capstone/auth/login.php">
      <div class="form-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required />
      </div>
      <div class="form-field">
        <label for="password">Password</label>
        <div class="password-field" style="position:relative;display:flex;align-items:center;">
          <input type="password" id="password" name="password" required style="padding-right:42px;" />
          <img src="/capstone/assets/img/eye.png" alt="Toggle password visibility" data-target="password" class="toggle-password" style="position:absolute;right:12px;width:22px;height:22px;cursor:pointer;opacity:.7;" />
        </div>
      </div>
      <button class="btn btn-primary center" type="submit">Login</button>
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

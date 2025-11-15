<?php $page='Contact'; include __DIR__.'/includes/header.php'; ?>
<section class="page-hero">
  <div class="container narrow">
    <h1>Contact Us</h1>
    <p class="lead">We'd love to hear from you. Send us a message and our team will respond shortly.</p>
  </div>
</section>
<section class="container narrow">
  <form class="card" method="post" action="#" onsubmit="alert('This is a demo UI. Hook this form to your backend.'); return false;">
    <div class="grid-2">
      <div class="form-field">
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" required />
      </div>
      <div class="form-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required />
      </div>
    </div>
    <div class="form-field">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="5" required></textarea>
    </div>
    <button class="btn btn-primary" type="submit">Send message</button>
  </form>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>

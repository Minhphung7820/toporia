<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home - Toporia Framework</title>

  <!-- Vite CSS (production only, dev handled by Vite) -->
  <?= vite_css('resources/js/app.js') ?>
</head>

<body>
  <div id="app">
    <h1>Home</h1>
    <p>
      <a href="/login">Login</a> |
      <a href="/products/create">Create Product</a> |
      <a href="/dashboard">Dashboard</a>
    </p>

    <?php if (!empty($user)): ?>
      <p>Hello, <?= htmlspecialchars($user['email']) ?>!</p>
      <p><a href="/logout">Logout</a></p>
    <?php else: ?>
      <p>You are not logged in</p>
    <?php endif; ?>
  </div>

  <!-- Vite JavaScript -->
  <?= vite('resources/js/app.js') ?>
</body>

</html>
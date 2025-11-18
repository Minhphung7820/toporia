<template>
  <div class="home-page">
    <div class="container">
      <div class="hero">
        <h1 class="hero-title">Welcome to Toporia</h1>
        <p class="hero-subtitle">A Professional PHP Framework Built on Clean Architecture</p>
        <div class="hero-actions">
          <router-link to="/about" class="btn btn-primary">Learn More</router-link>
        </div>
      </div>

      <div class="features">
        <h2 class="section-title">Key Features</h2>
        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">🏗️</div>
            <h3>Clean Architecture</h3>
            <p>Strict layer separation following SOLID principles</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <h3>High Performance</h3>
            <p>Optimized for speed and scalability</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🔒</div>
            <h3>Security First</h3>
            <p>Built-in CSRF, XSS protection, and security headers</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🚀</div>
            <h3>Modern Stack</h3>
            <p>Vue.js SPA with Vite for fast development</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Home',
};
</script>

<style scoped>
.home-page {
  min-height: calc(100vh - 200px);
}

.hero {
  text-align: center;
  padding: 4rem 0;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 12px;
  margin-bottom: 4rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.hero-title {
  font-size: 3.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
  animation: fadeInUp 0.6s ease;
}

.hero-subtitle {
  font-size: 1.5rem;
  margin-bottom: 2rem;
  opacity: 0.95;
  animation: fadeInUp 0.8s ease;
}

.hero-actions {
  animation: fadeInUp 1s ease;
}

.btn {
  display: inline-block;
  padding: 0.75rem 2rem;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background-color: white;
  color: #667eea;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.section-title {
  text-align: center;
  font-size: 2.5rem;
  margin-bottom: 3rem;
  color: #2c3e50;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 2rem;
  margin-bottom: 4rem;
}

.feature-card {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  text-align: center;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.feature-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.feature-card h3 {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
  color: #2c3e50;
}

.feature-card p {
  color: #666;
  line-height: 1.6;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .hero-title {
    font-size: 2.5rem;
  }

  .hero-subtitle {
    font-size: 1.2rem;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }
}
</style>





<template>
  <div class="about-page">
    <div class="container">
      <div class="page-header">
        <h1 class="page-title">About Toporia</h1>
        <p class="page-subtitle">Learn more about our framework</p>
      </div>

      <div class="content-section">
        <div class="content-card">
          <h2>What is Toporia?</h2>
          <p>
            Toporia is a modern, professional-grade PHP framework designed with
            <strong>Clean Architecture</strong> and <strong>SOLID principles</strong> at its core.
            It provides a robust foundation for building scalable web applications while maintaining
            strict separation of concerns between framework and application layers.
          </p>
        </div>

        <div class="content-card">
          <h2>Architecture</h2>
          <p>
            Toporia follows <strong>Clean Architecture</strong> principles with strict layer separation:
          </p>
          <ul class="feature-list">
            <li><strong>Domain Layer:</strong> Business logic and entities</li>
            <li><strong>Application Layer:</strong> Use cases and business rules</li>
            <li><strong>Infrastructure Layer:</strong> External concerns (database, APIs)</li>
            <li><strong>Presentation Layer:</strong> Controllers, views, and HTTP handling</li>
          </ul>
        </div>

        <div class="content-card">
          <h2>Technology Stack</h2>
          <div class="tech-grid">
            <div class="tech-item">
              <strong>Backend:</strong> PHP 8.1+
            </div>
            <div class="tech-item">
              <strong>Frontend:</strong> Vue.js 3 + Vue Router
            </div>
            <div class="tech-item">
              <strong>Build Tool:</strong> Vite
            </div>
            <div class="tech-item">
              <strong>Architecture:</strong> Clean Architecture + SOLID
            </div>
          </div>
        </div>

        <div class="content-card">
          <h2>Key Features</h2>
          <ul class="feature-list">
            <li>✅ Zero framework dependencies (core requires only PHP 8.1+)</li>
            <li>✅ Dependency Injection with auto-wiring</li>
            <li>✅ Service Provider pattern for modular services</li>
            <li>✅ Queue system for async processing</li>
            <li>✅ Built-in security features (CSRF, XSS protection)</li>
            <li>✅ Vue.js SPA support with Vite</li>
          </ul>
        </div>

        <div class="content-actions">
          <router-link to="/" class="btn btn-secondary">Back to Home</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'About',
};
</script>

<style scoped>
.about-page {
  min-height: calc(100vh - 200px);
}

.page-header {
  text-align: center;
  padding: 2rem 0;
  margin-bottom: 3rem;
}

.page-title {
  font-size: 3rem;
  color: #2c3e50;
  margin-bottom: 0.5rem;
}

.page-subtitle {
  font-size: 1.2rem;
  color: #666;
}

.content-section {
  max-width: 800px;
  margin: 0 auto;
}

.content-card {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.content-card h2 {
  color: #667eea;
  margin-bottom: 1rem;
  font-size: 1.8rem;
}

.content-card p {
  color: #555;
  line-height: 1.8;
  margin-bottom: 1rem;
}

.feature-list {
  list-style: none;
  padding-left: 0;
}

.feature-list li {
  padding: 0.5rem 0;
  color: #555;
  line-height: 1.8;
  padding-left: 1.5rem;
  position: relative;
}

.feature-list li::before {
  content: '→';
  position: absolute;
  left: 0;
  color: #667eea;
  font-weight: bold;
}

.tech-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.tech-item {
  padding: 1rem;
  background: #f8f9fa;
  border-radius: 8px;
  text-align: center;
  color: #555;
}

.content-actions {
  text-align: center;
  margin-top: 3rem;
}

.btn-secondary {
  background-color: #667eea;
  color: white;
}

.btn-secondary:hover {
  background-color: #5568d3;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

@media (max-width: 768px) {
  .page-title {
    font-size: 2rem;
  }

  .content-card {
    padding: 1.5rem;
  }

  .tech-grid {
    grid-template-columns: 1fr;
  }
}
</style>


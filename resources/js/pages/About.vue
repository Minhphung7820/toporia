<template>
  <div class="about-page">
    <!-- Hero Section -->
    <section class="about-hero">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">About Toporia</h1>
          <p class="hero-subtitle">A modern PHP framework built on Clean Architecture and SOLID principles</p>
        </div>
      </div>
    </section>

    <!-- Content Section -->
    <section class="content-section">
      <div class="container">
        <!-- What is Toporia -->
        <div class="content-card">
          <div class="card-header">
            <div class="card-icon">🚀</div>
            <h2 class="card-title">What is Toporia?</h2>
          </div>
          <div class="card-body">
            <p>
              Toporia is a modern, professional-grade PHP framework designed with
              <strong>Clean Architecture</strong> and <strong>SOLID principles</strong> at its core.
              It provides a robust foundation for building scalable web applications while maintaining
              strict separation of concerns between framework and application layers.
            </p>
            <p>
              Inspired by modern PHP frameworks and enterprise architecture patterns, Toporia offers a
              <strong>zero-dependency</strong> core framework with optional integrations, giving you full
              control over your application's architecture.
            </p>
          </div>
        </div>

        <!-- Architecture -->
        <div class="content-card">
          <div class="card-header">
            <div class="card-icon">🏗️</div>
            <h2 class="card-title">Architecture</h2>
          </div>
          <div class="card-body">
            <p>
              Toporia follows <strong>Clean Architecture</strong> principles with strict layer separation:
            </p>
            <div class="architecture-grid">
              <div class="arch-item" v-for="(layer, index) in architectureLayers" :key="index">
                <div class="arch-number">{{ index + 1 }}</div>
                <div class="arch-content">
                  <h3 class="arch-title">{{ layer.name }}</h3>
                  <p class="arch-description">{{ layer.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Technology Stack -->
        <div class="content-card">
          <div class="card-header">
            <div class="card-icon">⚙️</div>
            <h2 class="card-title">Technology Stack</h2>
          </div>
          <div class="card-body">
            <div class="tech-grid">
              <div class="tech-item" v-for="(tech, index) in techStack" :key="index">
                <div class="tech-icon">{{ tech.icon }}</div>
                <div class="tech-content">
                  <h3 class="tech-name">{{ tech.name }}</h3>
                  <p class="tech-desc">{{ tech.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Key Features -->
        <div class="content-card">
          <div class="card-header">
            <div class="card-icon">✨</div>
            <h2 class="card-title">Key Features</h2>
          </div>
          <div class="card-body">
            <div class="features-list">
              <div class="feature-item" v-for="(feature, index) in keyFeatures" :key="index">
                <div class="feature-check">✓</div>
                <span class="feature-text">{{ feature }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="content-actions">
          <router-link to="/" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Back to Home</span>
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: 'About',
  data() {
    return {
      architectureLayers: [
        {
          name: 'Domain Layer',
          description: 'Pure business logic and entities with no framework dependencies'
        },
        {
          name: 'Application Layer',
          description: 'Use cases, business rules, and application services'
        },
        {
          name: 'Infrastructure Layer',
          description: 'Repository implementations, external services, and integrations'
        },
        {
          name: 'Presentation Layer',
          description: 'Controllers, views, middleware, and HTTP handling'
        }
      ],
      techStack: [
        {
          icon: '🐘',
          name: 'Backend',
          description: 'PHP 8.1+ with modern features'
        },
        {
          icon: '💚',
          name: 'Frontend',
          description: 'Vue.js 3 + Vue Router'
        },
        {
          icon: '⚡',
          name: 'Build Tool',
          description: 'Vite for fast development'
        },
        {
          icon: '🏛️',
          name: 'Architecture',
          description: 'Clean Architecture + SOLID'
        }
      ],
      keyFeatures: [
        'Zero framework dependencies (core requires only PHP 8.1+)',
        'Dependency Injection with auto-wiring',
        'Service Provider pattern for modular services',
        'Queue system for async processing',
        'Built-in security features (CSRF, XSS protection)',
        'Vue.js SPA support with Vite',
        'Multi-database support (MySQL, PostgreSQL, SQLite, MongoDB)',
        'Real-time broadcasting (WebSocket, SSE, Kafka, RabbitMQ)',
        'Elasticsearch integration',
        'Comprehensive ORM with relationships'
      ]
    };
  }
};
</script>

<style scoped>
.about-page {
  min-height: calc(100vh - 200px);
  background: #f9fafb;
}

/* Hero Section */
.about-hero {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 80px 0;
  text-align: center;
}

.hero-content {
  max-width: 800px;
  margin: 0 auto;
}

.hero-title {
  font-size: clamp(36px, 6vw, 56px);
  font-weight: 800;
  margin-bottom: 16px;
  letter-spacing: -0.02em;
}

.hero-subtitle {
  font-size: clamp(18px, 2.5vw, 22px);
  opacity: 0.95;
  font-weight: 400;
}

/* Content Section */
.content-section {
  padding: 80px 0;
}

.container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 24px;
}

.content-card {
  background: white;
  border-radius: 16px;
  padding: 40px;
  margin-bottom: 32px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.content-card:hover {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  transform: translateY(-2px);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 2px solid #f3f4f6;
}

.card-icon {
  font-size: 32px;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  color: white;
}

.card-title {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.card-body {
  color: #6b7280;
  line-height: 1.8;
}

.card-body p {
  margin-bottom: 16px;
  font-size: 16px;
}

.card-body p:last-child {
  margin-bottom: 0;
}

.card-body strong {
  color: #111827;
  font-weight: 600;
}

/* Architecture Grid */
.architecture-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 24px;
  margin-top: 24px;
}

.arch-item {
  display: flex;
  gap: 16px;
  padding: 20px;
  background: #f9fafb;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.arch-item:hover {
  background: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}

.arch-number {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 10px;
  font-weight: 700;
  font-size: 18px;
  flex-shrink: 0;
}

.arch-content {
  flex: 1;
}

.arch-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.arch-description {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
}

/* Tech Grid */
.tech-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 24px;
  margin-top: 24px;
}

.tech-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 32px 24px;
  background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.tech-item:hover {
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
  transform: translateY(-4px);
  border-color: #d1d5db;
}

.tech-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.tech-name {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.tech-desc {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

/* Features List */
.features-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 16px;
  margin-top: 24px;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.feature-item:hover {
  background: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.feature-check {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border-radius: 6px;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.feature-text {
  font-size: 15px;
  color: #374151;
  line-height: 1.6;
  flex: 1;
}

/* Actions */
.content-actions {
  text-align: center;
  margin-top: 48px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn svg {
  flex-shrink: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .about-hero {
    padding: 60px 0;
  }

  .content-section {
    padding: 60px 0;
  }

  .content-card {
    padding: 24px;
  }

  .card-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }

  .card-icon {
    width: 48px;
    height: 48px;
    font-size: 24px;
  }

  .card-title {
    font-size: 24px;
  }

  .architecture-grid {
    grid-template-columns: 1fr;
  }

  .tech-grid {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }

  .features-list {
    grid-template-columns: 1fr;
  }

  .container {
    padding: 0 20px;
  }
}

@media (max-width: 480px) {
  .tech-grid {
    grid-template-columns: 1fr;
  }

  .content-card {
    padding: 20px;
  }
}
</style>
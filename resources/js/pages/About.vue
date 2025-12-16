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
  min-height: calc(100vh - 120px);
  background: #fafafa;
}

/* Hero Section */
.about-hero {
  background: #fff;
  border-bottom: 1px solid #e5e5e5;
  padding: 60px 0;
  text-align: center;
}

.hero-content {
  max-width: 700px;
  margin: 0 auto;
}

.hero-title {
  font-size: clamp(32px, 5vw, 48px);
  font-weight: 700;
  margin-bottom: 12px;
  color: #1a1a1a;
}

.hero-subtitle {
  font-size: clamp(16px, 2vw, 18px);
  color: #666;
  font-weight: 400;
}

/* Content Section */
.content-section {
  padding: 60px 0;
}

.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 24px;
}

.content-card {
  background: #fff;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 24px;
  border: 1px solid #e5e5e5;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.card-icon {
  font-size: 28px;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
  border-radius: 10px;
}

.card-title {
  font-size: 24px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.card-body {
  color: #666;
  line-height: 1.7;
}

.card-body p {
  margin-bottom: 14px;
  font-size: 15px;
}

.card-body p:last-child {
  margin-bottom: 0;
}

.card-body strong {
  color: #1a1a1a;
  font-weight: 600;
}

/* Architecture Grid */
.architecture-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-top: 20px;
}

.arch-item {
  display: flex;
  gap: 14px;
  padding: 16px;
  background: #fafafa;
  border-radius: 10px;
  border: 1px solid #e5e5e5;
}

.arch-number {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1a1a1a;
  color: white;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}

.arch-content {
  flex: 1;
}

.arch-title {
  font-size: 15px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 4px;
}

.arch-description {
  font-size: 13px;
  color: #666;
  line-height: 1.5;
  margin: 0;
}

/* Tech Grid */
.tech-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-top: 20px;
}

.tech-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 24px 16px;
  background: #fafafa;
  border-radius: 10px;
  border: 1px solid #e5e5e5;
}

.tech-icon {
  font-size: 36px;
  margin-bottom: 12px;
}

.tech-name {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 6px;
}

.tech-desc {
  font-size: 13px;
  color: #666;
  margin: 0;
}

/* Features List */
.features-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
  margin-top: 20px;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  background: #fafafa;
  border-radius: 8px;
  border: 1px solid #e5e5e5;
}

.feature-check {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #16a34a;
  color: white;
  border-radius: 5px;
  font-weight: 600;
  font-size: 12px;
  flex-shrink: 0;
}

.feature-text {
  font-size: 14px;
  color: #444;
  line-height: 1.5;
  flex: 1;
}

/* Actions */
.content-actions {
  text-align: center;
  margin-top: 40px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: #1a1a1a;
  color: white;
}

.btn-primary:hover {
  background: #333;
}

.btn svg {
  flex-shrink: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .about-hero {
    padding: 40px 0;
  }

  .content-section {
    padding: 40px 0;
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
    width: 40px;
    height: 40px;
    font-size: 22px;
  }

  .card-title {
    font-size: 20px;
  }

  .architecture-grid {
    grid-template-columns: 1fr;
  }

  .tech-grid {
    grid-template-columns: repeat(2, 1fr);
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

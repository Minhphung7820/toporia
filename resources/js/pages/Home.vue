<template>
  <div class="welcome-page">
    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="hero-content">
          <div class="version-badge">
            <span class="badge-dot"></span>
            Version {{ version }}
          </div>
          <h1 class="hero-title">{{ frameworkName }}</h1>
          <p class="hero-subtitle">
            A Professional PHP Framework Built on Clean Architecture Principles
          </p>
          <div class="hero-actions">
            <router-link to="/about" class="btn btn-primary">
              <span>Get Started</span>
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </router-link>
            <a href="https://github.com/tmp-dev/toporia" target="_blank" class="btn btn-secondary">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 2C5.58 2 2 5.58 2 10C2 13.42 4.16 16.34 7.25 17.75C7.75 17.84 7.92 17.5 7.92 17.21C7.92 16.96 7.91 16.25 7.91 15.5C5.5 16 4.84 14.84 4.84 14.84C4.5 13.92 3.92 13.67 3.92 13.67C3.17 13.17 4.08 13.17 4.08 13.17C4.92 13.25 5.33 14.08 5.33 14.08C6.08 15.25 7.17 14.84 7.92 14.59C8 14.08 8.17 13.75 8.34 13.59C6.5 13.34 4.58 12.59 4.58 9.5C4.58 8.5 4.92 7.75 5.34 7.17C5.25 6.92 4.92 5.84 5.5 4.5C5.5 4.5 6.42 4.17 7.92 5.42C8.58 5.25 9.25 5.17 9.92 5.17C10.59 5.17 11.25 5.25 11.92 5.42C13.42 4.17 14.34 4.5 14.34 4.5C14.92 5.84 14.59 6.92 14.5 7.17C14.92 7.75 15.25 8.5 15.25 9.5C15.25 12.59 13.34 13.34 11.5 13.59C11.75 13.84 12 14.25 12 14.84C12 15.75 12 16.5 12 17.21C12 17.5 12.17 17.84 12.67 17.75C15.84 16.34 18 13.42 18 10C18 5.58 14.42 2 10 2Z" fill="currentColor"/>
              </svg>
              <span>GitHub</span>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
      <div class="container">
        <div class="section-header">
          <h2 class="section-title">Key Features</h2>
          <p class="section-description">
            {{ frameworkName }} has an incredibly rich ecosystem. We suggest starting with the following.
          </p>
        </div>

        <div class="features-grid">
          <div class="feature-card" v-for="(feature, index) in features" :key="index">
            <div class="feature-icon">
              <span>{{ feature.emoji }}</span>
            </div>
            <h3 class="feature-title">{{ feature.title }}</h3>
            <p class="feature-description">{{ feature.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Info Cards Section -->
    <section class="info-section">
      <div class="container">
        <div class="info-grid">
          <div class="info-card" v-for="(info, index) in infoCards" :key="index">
            <div class="info-icon">{{ info.icon }}</div>
            <h3 class="info-title">{{ info.title }}</h3>
            <p class="info-description">{{ info.description }}</p>
            <a v-if="info.link" :href="info.link" target="_blank" class="info-link">
              Learn more
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="page-footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-links">
            <a href="https://github.com/tmp-dev/toporia" target="_blank" class="footer-link">
              <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 2C5.58 2 2 5.58 2 10C2 13.42 4.16 16.34 7.25 17.75C7.75 17.84 7.92 17.5 7.92 17.21C7.92 16.96 7.91 16.25 7.91 15.5C5.5 16 4.84 14.84 4.84 14.84C4.5 13.92 3.92 13.67 3.92 13.67C3.17 13.17 4.08 13.17 4.08 13.17C4.92 13.25 5.33 14.08 5.33 14.08C6.08 15.25 7.17 14.84 7.92 14.59C8 14.08 8.17 13.75 8.34 13.59C6.5 13.34 4.58 12.59 4.58 9.5C4.58 8.5 4.92 7.75 5.34 7.17C5.25 6.92 4.92 5.84 5.5 4.5C5.5 4.5 6.42 4.17 7.92 5.42C8.58 5.25 9.25 5.17 9.92 5.17C10.59 5.17 11.25 5.25 11.92 5.42C13.42 4.17 14.34 4.5 14.34 4.5C14.92 5.84 14.59 6.92 14.5 7.17C14.92 7.75 15.25 8.5 15.25 9.5C15.25 12.59 13.34 13.34 11.5 13.59C11.75 13.84 12 14.25 12 14.84C12 15.75 12 16.5 12 17.21C12 17.5 12.17 17.84 12.67 17.75C15.84 16.34 18 13.42 18 10C18 5.58 14.42 2 10 2Z"/>
              </svg>
              GitHub
            </a>
            <a href="https://github.com/tmp-dev/toporia/blob/main/README.md" target="_blank" class="footer-link">
              Documentation
            </a>
            <a href="#" class="footer-link">Support</a>
          </div>
          <div class="footer-meta">
            <p>PHP {{ phpVersion }}+ | {{ frameworkName }} v{{ version }}</p>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
export default {
  name: 'Home',
  data() {
    return {
      version: '1.0.0',
      frameworkName: 'TOPORIA',
      phpVersion: '8.1',
      features: [
        {
          title: 'Clean Architecture',
          description: 'Strict layer separation with Domain, Application, Infrastructure, and Presentation layers',
          emoji: '🏗️',
        },
        {
          title: 'SOLID Principles',
          description: 'Every component follows Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion',
          emoji: '✨',
        },
        {
          title: 'Zero-Dependency Core',
          description: 'Core framework requires only PHP 8.1+, giving you full control over your application architecture',
          emoji: '🚀',
        },
        {
          title: 'Intuitive Developer Experience',
          description: 'Elegant, expressive syntax with fluent APIs that feel natural and boost productivity',
          emoji: '🔧',
        },
        {
          title: 'Rich Ecosystem',
          description: 'Comprehensive package ecosystem with Queue, Cache, Realtime, Search, and more',
          emoji: '📦',
        },
        {
          title: 'Performance Optimized',
          description: 'Built for speed with O(1) container resolution, lazy loading, and query optimization',
          emoji: '⚡',
        }
      ],
      infoCards: [
        {
          icon: '📚',
          title: 'Documentation',
          description: 'Comprehensive documentation covering all aspects of the framework, from installation to advanced topics.',
          link: 'https://github.com/tmp-dev/toporia'
        },
        {
          icon: '💡',
          title: 'Examples',
          description: 'Explore real-world examples and code samples to quickly understand how to build applications.',
          link: null
        },
        {
          icon: '👥',
          title: 'Community',
          description: 'Join the growing community of developers building amazing applications with Toporia.',
          link: 'https://github.com/tmp-dev/toporia'
        }
      ]
    };
  },
  mounted() {
    // You can fetch version info from API if needed
    // For now, using static values
  },
};
</script>

<style scoped>
.welcome-page {
  min-height: 100vh;
  background: #fafafa;
  color: #1a1a1a;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
}

.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 2rem;
}

/* Hero Section */
.hero-section {
  padding: 100px 0 80px;
  text-align: center;
  background: #fff;
  border-bottom: 1px solid #e5e5e5;
}

.hero-content {
  max-width: 700px;
  margin: 0 auto;
}

.version-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f5f5f5;
  color: #666;
  padding: 8px 16px;
  border-radius: 50px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 24px;
  border: 1px solid #e5e5e5;
}

.badge-dot {
  width: 8px;
  height: 8px;
  background: #16a34a;
  border-radius: 50%;
}

.hero-title {
  font-size: clamp(48px, 8vw, 80px);
  font-weight: 800;
  letter-spacing: -0.03em;
  margin-bottom: 20px;
  line-height: 1.1;
  color: #1a1a1a;
}

.hero-subtitle {
  font-size: clamp(18px, 2.5vw, 22px);
  color: #666;
  font-weight: 400;
  max-width: 600px;
  margin: 0 auto 40px;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
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

.btn-secondary {
  background: #fff;
  color: #1a1a1a;
  border: 1px solid #ddd;
}

.btn-secondary:hover {
  background: #f5f5f5;
  border-color: #ccc;
}

.btn svg {
  flex-shrink: 0;
}

/* Features Section */
.features-section {
  padding: 80px 0;
  background: #fafafa;
}

.section-header {
  text-align: center;
  margin-bottom: 60px;
}

.section-title {
  font-size: clamp(28px, 4vw, 40px);
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 12px;
}

.section-description {
  font-size: 16px;
  color: #666;
  max-width: 500px;
  margin: 0 auto;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.feature-card {
  background: #fff;
  border: 1px solid #e5e5e5;
  border-radius: 12px;
  padding: 32px;
  transition: all 0.2s;
}

.feature-card:hover {
  border-color: #ccc;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.feature-icon {
  width: 48px;
  height: 48px;
  background: #f5f5f5;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin-bottom: 16px;
}

.feature-title {
  font-size: 18px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 10px;
}

.feature-description {
  font-size: 14px;
  color: #666;
  line-height: 1.6;
}

/* Info Section */
.info-section {
  padding: 80px 0;
  background: #fff;
  border-top: 1px solid #e5e5e5;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.info-card {
  background: #fafafa;
  border-radius: 12px;
  padding: 32px;
  border: 1px solid #e5e5e5;
  transition: all 0.2s;
}

.info-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.info-icon {
  font-size: 40px;
  margin-bottom: 16px;
  display: block;
}

.info-title {
  font-size: 20px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 10px;
}

.info-description {
  font-size: 14px;
  color: #666;
  line-height: 1.6;
  margin-bottom: 16px;
}

.info-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #1a1a1a;
  text-decoration: none;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.2s;
}

.info-link:hover {
  color: #666;
}

/* Footer */
.page-footer {
  background: #1a1a1a;
  color: #999;
  padding: 48px 0;
}

.footer-content {
  text-align: center;
}

.footer-links {
  display: flex;
  justify-content: center;
  gap: 32px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}

.footer-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #999;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s;
}

.footer-link:hover {
  color: #fff;
}

.footer-link svg {
  width: 18px;
  height: 18px;
}

.footer-meta {
  padding-top: 24px;
  border-top: 1px solid #333;
}

.footer-meta p {
  font-size: 14px;
  color: #666;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .info-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .hero-section {
    padding: 60px 0 50px;
  }

  .hero-actions {
    flex-direction: column;
    width: 100%;
    max-width: 300px;
    margin: 0 auto;
  }

  .btn {
    width: 100%;
    justify-content: center;
  }

  .features-section {
    padding: 60px 0;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }

  .info-section {
    padding: 60px 0;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .footer-links {
    flex-direction: column;
    gap: 16px;
  }

  .container {
    padding: 0 1.5rem;
  }
}

@media (max-width: 480px) {
  .hero-section {
    padding: 40px 0;
  }

  .feature-card,
  .info-card {
    padding: 24px;
  }

  .page-footer {
    padding: 32px 0;
  }
}
</style>

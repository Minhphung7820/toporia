<template>
  <div class="welcome-page">
    <!-- Animated Background -->
    <div class="animated-bg">
      <div class="gradient-orb orb-1"></div>
      <div class="gradient-orb orb-2"></div>
      <div class="gradient-orb orb-3"></div>
    </div>

    <!-- Hero Section -->
    <section class="hero-section">
      <div class="container">
        <div class="hero-content">
          <div class="version-badge">
            <span class="badge-dot"></span>
            Version {{ version }}
          </div>
          <h1 class="hero-title">
            <span class="title-gradient">{{ frameworkName }}</span>
          </h1>
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
            <div class="feature-icon-wrapper">
              <div class="feature-icon" :class="feature.iconClass">
                <component :is="feature.icon" v-if="feature.icon" />
                <span v-else>{{ feature.emoji }}</span>
              </div>
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
          iconClass: 'icon-architecture'
        },
        {
          title: 'SOLID Principles',
          description: 'Every component follows Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion',
          emoji: '✨',
          iconClass: 'icon-solid'
        },
        {
          title: 'Zero-Dependency Core',
          description: 'Core framework requires only PHP 8.1+, giving you full control over your application architecture',
          emoji: '🚀',
          iconClass: 'icon-zero'
        },
        {
          title: 'Intuitive Developer Experience',
          description: 'Elegant, expressive syntax with fluent APIs that feel natural and boost productivity',
          emoji: '🔧',
          iconClass: 'icon-dx'
        },
        {
          title: 'Rich Ecosystem',
          description: 'Comprehensive package ecosystem with Queue, Cache, Realtime, Search, and more',
          emoji: '📦',
          iconClass: 'icon-ecosystem'
        },
        {
          title: 'Performance Optimized',
          description: 'Built for speed with O(1) container resolution, lazy loading, and query optimization',
          emoji: '⚡',
          iconClass: 'icon-performance'
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
  position: relative;
  background: #ffffff;
  color: #1f2937;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
  overflow-x: hidden;
}

.container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

/* Animated Background */
.animated-bg {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
}

.gradient-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.3;
  animation: float 20s ease-in-out infinite;
}

.orb-1 {
  width: 500px;
  height: 500px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  top: -250px;
  right: -250px;
  animation-duration: 25s;
}

.orb-2 {
  width: 400px;
  height: 400px;
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  bottom: -200px;
  left: -200px;
  animation-duration: 30s;
  animation-delay: -10s;
}

.orb-3 {
  width: 300px;
  height: 300px;
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation-duration: 35s;
  animation-delay: -5s;
}

@keyframes float {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  33% {
    transform: translate(30px, -30px) scale(1.1);
  }
  66% {
    transform: translate(-30px, 30px) scale(0.9);
  }
}

/* Hero Section */
.hero-section {
  position: relative;
  z-index: 1;
  padding: 120px 0 80px;
  text-align: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  overflow: hidden;
}

.hero-content {
  position: relative;
  z-index: 2;
}

.version-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  color: white;
  padding: 8px 16px;
  border-radius: 50px;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 24px;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.badge-dot {
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.7;
    transform: scale(1.2);
  }
}

.hero-title {
  font-size: clamp(48px, 8vw, 96px);
  font-weight: 800;
  letter-spacing: -0.03em;
  margin-bottom: 24px;
  line-height: 1.1;
}

.title-gradient {
  background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.9) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: clamp(18px, 2.5vw, 24px);
  opacity: 0.95;
  font-weight: 400;
  max-width: 700px;
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
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary {
  background: #000000;
  color: white;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
}

.btn-primary:hover {
  background: #1a1a1a;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-2px);
}

.btn svg {
  flex-shrink: 0;
}

/* Features Section */
.features-section {
  position: relative;
  z-index: 1;
  padding: 100px 0;
  background: #ffffff;
}

.section-header {
  text-align: center;
  margin-bottom: 64px;
}

.section-title {
  font-size: clamp(32px, 5vw, 48px);
  font-weight: 700;
  color: #111827;
  margin-bottom: 16px;
  letter-spacing: -0.02em;
}

.section-description {
  font-size: 18px;
  color: #6b7280;
  max-width: 600px;
  margin: 0 auto;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 32px;
}

.feature-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 32px;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.feature-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.feature-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  transform: translateY(-4px);
}

.feature-card:hover::before {
  transform: scaleX(1);
}

.feature-icon-wrapper {
  margin-bottom: 20px;
}

.feature-icon {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: white;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.feature-title {
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 12px;
}

.feature-description {
  font-size: 15px;
  color: #6b7280;
  line-height: 1.6;
}

/* Info Section */
.info-section {
  position: relative;
  z-index: 1;
  padding: 80px 0 100px;
  background: #f9fafb;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 32px;
}

.info-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 40px;
  transition: all 0.3s ease;
  border: 1px solid #e5e7eb;
}

.info-card:hover {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  transform: translateY(-4px);
}

.info-icon {
  font-size: 48px;
  margin-bottom: 20px;
  display: block;
}

.info-title {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 12px;
}

.info-description {
  font-size: 15px;
  color: #6b7280;
  line-height: 1.6;
  margin-bottom: 20px;
}

.info-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s ease;
}

.info-link:hover {
  color: #764ba2;
  gap: 10px;
}

/* Footer */
.page-footer {
  position: relative;
  z-index: 1;
  background: #111827;
  color: #9ca3af;
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
  color: #9ca3af;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s ease;
}

.footer-link:hover {
  color: #ffffff;
}

.footer-link svg {
  width: 18px;
  height: 18px;
}

.footer-meta {
  padding-top: 24px;
  border-top: 1px solid #374151;
}

.footer-meta p {
  font-size: 14px;
  color: #6b7280;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .features-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
  }

  .info-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
  }
}

@media (max-width: 768px) {
  .hero-section {
    padding: 80px 0 60px;
  }

  .hero-actions {
    flex-direction: column;
    width: 100%;
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
    gap: 24px;
  }

  .info-section {
    padding: 60px 0 80px;
  }

  .info-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .footer-links {
    flex-direction: column;
    gap: 16px;
  }

  .container {
    padding: 0 20px;
  }
}

@media (max-width: 480px) {
  .hero-section {
    padding: 60px 0 40px;
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

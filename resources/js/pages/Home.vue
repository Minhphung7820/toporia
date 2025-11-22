<template>
  <div class="welcome-page">
    <div class="geometric-bg">
      <div class="geometric-shape shape-1"></div>
      <div class="geometric-shape shape-2"></div>
    </div>

    <div class="header">
    <div class="container">
        <div class="version-badge">Version {{ version }}</div>
        <h1 class="logo">{{ frameworkName }}</h1>
        <p class="version">A Professional PHP Framework Built on Clean Architecture Principles</p>
      </div>
    </div>

    <div class="main">
      <div class="container">
        <section class="welcome-section">
          <h2 class="welcome-title">Let's get started</h2>
          <p class="welcome-description">
            {{ frameworkName }} has an incredibly rich ecosystem. We suggest starting with the following.
          </p>

          <div class="links">
            <router-link to="/about" class="link-item link-primary">
              Get Started
            </router-link>
            <a href="https://github.com/tmp-dev/toporia" target="_blank" class="link-item">
              Read the Documentation
            </a>
            <a href="https://github.com/tmp-dev/toporia" target="_blank" class="link-item">
              Watch Video Tutorials
            </a>
          </div>
        </section>

        <section class="features">
          <h3 class="features-title">Key Features</h3>
        <div class="features-grid">
            <div class="feature-item">
            <div class="feature-icon">🏗️</div>
              <div class="feature-text">Clean Architecture with strict layer separation</div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">✨</div>
              <div class="feature-text">SOLID Principles in every component</div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">🚀</div>
              <div class="feature-text">Zero-dependency core framework</div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">🔧</div>
              <div class="feature-text">Laravel-compatible API</div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">📦</div>
              <div class="feature-text">Comprehensive package ecosystem</div>
            </div>
            <div class="feature-item">
              <div class="feature-icon">⚡</div>
              <div class="feature-text">Performance optimized</div>
            </div>
          </div>
        </section>

        <div class="info-grid">
          <div class="info-card">
            <h3 class="info-card-title">Documentation</h3>
            <p class="info-card-description">
              Comprehensive documentation covering all aspects of the framework, from installation to advanced topics.
            </p>
          </div>
          <div class="info-card">
            <h3 class="info-card-title">Examples</h3>
            <p class="info-card-description">
              Explore real-world examples and code samples to quickly understand how to build applications.
            </p>
          </div>
          <div class="info-card">
            <h3 class="info-card-title">Community</h3>
            <p class="info-card-description">
              Join the growing community of developers building amazing applications with {{ frameworkName }}.
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="footer">
      <div class="container">
        <div class="footer-links">
          <a href="https://github.com/tmp-dev/toporia" target="_blank" class="footer-link">GitHub</a>
          <a href="https://github.com/tmp-dev/toporia/blob/main/README.md" target="_blank" class="footer-link">Documentation</a>
          <a href="#" class="footer-link">Support</a>
        </div>
        <p>
          PHP Version {{ phpVersion }} | {{ frameworkName }} Framework v{{ version }}
        </p>
      </div>
    </div>
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
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
  line-height: 1.6;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Geometric Background Element */
.geometric-bg {
  position: absolute;
  width: 100%;
  height: 100%;
  overflow: hidden;
  z-index: 0;
}

.geometric-shape {
  position: absolute;
  opacity: 0.1;
}

.shape-1 {
  width: 300px;
  height: 300px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
  top: -100px;
  right: -100px;
  animation: float 20s ease-in-out infinite;
}

.shape-2 {
  width: 250px;
  height: 250px;
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  border-radius: 70% 30% 30% 70% / 70% 70% 30% 30%;
  bottom: -100px;
  left: -100px;
  animation: float 25s ease-in-out infinite reverse;
}

@keyframes float {
  0%, 100% {
    transform: translate(0, 0) rotate(0deg);
  }
  50% {
    transform: translate(30px, 30px) rotate(180deg);
  }
}

/* Header */
.header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 80px 20px;
  text-align: center;
  color: white;
  position: relative;
  z-index: 1;
}

.logo {
  font-size: 72px;
  font-weight: 700;
  letter-spacing: -0.02em;
  margin-bottom: 10px;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.version {
  font-size: 18px;
  opacity: 0.9;
  font-weight: 400;
}

/* Version Badge */
.version-badge {
  display: inline-block;
  background: #10b981;
  color: white;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 20px;
}

/* Main Content */
.main {
  padding: 60px 20px;
  background: #ffffff;
  position: relative;
  z-index: 1;
}

.welcome-section {
  text-align: center;
  margin-bottom: 60px;
}

.welcome-title {
  font-size: 32px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 16px;
}

.welcome-description {
  font-size: 18px;
  color: #6b7280;
  max-width: 700px;
  margin: 0 auto 40px;
}

/* Links Section */
.links {
  display: flex;
  justify-content: center;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 60px;
}

.link-item {
  position: relative;
  text-decoration: none;
  color: #ef4444;
  font-size: 16px;
  font-weight: 500;
  padding: 12px 24px;
  border-radius: 8px;
  transition: all 0.2s;
}

.link-item:hover {
  background: #fef2f2;
  transform: translateY(-2px);
}

.link-item.link-primary {
  background: #000;
  color: #fff;
  padding: 14px 32px;
  font-weight: 600;
}

.link-item.link-primary:hover {
  background: #1f1f1f;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.link-item::after {
  content: ' ↗';
  opacity: 0.7;
}

.link-item.link-primary::after {
  display: none;
}

/* Features List */
.features {
  background: #f9fafb;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 40px;
}

.features-title {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 20px;
  text-align: center;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.feature-icon {
  width: 24px;
  height: 24px;
  background: #667eea;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  flex-shrink: 0;
}

.feature-text {
  color: #374151;
  font-size: 15px;
}

/* Info Cards */
.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-bottom: 60px;
  }

.info-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  transition: all 0.2s;
  }

.info-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  }

.info-card-title {
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.info-card-description {
  color: #6b7280;
  font-size: 14px;
    line-height: 1.5;
  }

/* Footer */
.footer {
  text-align: center;
  padding: 40px 20px;
  color: #6b7280;
  border-top: 1px solid #e5e7eb;
  position: relative;
  z-index: 1;
  }

.footer-links {
  display: flex;
  justify-content: center;
  gap: 24px;
  flex-wrap: wrap;
  margin-bottom: 20px;
  }

.footer-link {
  color: #6b7280;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s;
}

.footer-link:hover {
  color: #111827;
  }

/* Responsive */
@media (max-width: 768px) {
  .logo {
    font-size: 48px;
  }

  .welcome-title {
    font-size: 24px;
  }

  .welcome-description {
    font-size: 16px;
  }

  .links {
    flex-direction: column;
    align-items: center;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .header {
    padding: 60px 20px;
  }

  .main {
    padding: 40px 20px;
  }
}
</style>

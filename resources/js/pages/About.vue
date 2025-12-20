<template>
  <div class="about-page">
    <!-- Hero Section -->
    <section class="about-hero">
      <div class="container">
        <div class="hero-content">
          <h1 class="hero-title">About Toporia</h1>
          <p class="hero-subtitle">
            A community-driven platform where developers share knowledge, learn together, and grow their skills
          </p>
        </div>
      </div>
    </section>

    <!-- Mission Section -->
    <section class="mission-section">
      <div class="container">
        <div class="mission-grid">
          <div class="mission-content">
            <span class="section-label">Our Mission</span>
            <h2 class="section-title">Empowering Developers Worldwide</h2>
            <p class="section-text">
              Toporia was created with a simple belief: knowledge should be accessible to everyone.
              We're building a space where developers of all levels can find quality tutorials,
              share their experiences, and connect with like-minded individuals.
            </p>
            <p class="section-text">
              Whether you're just starting your coding journey or you're a seasoned professional,
              Toporia provides the resources and community you need to succeed.
            </p>
          </div>
          <div class="mission-stats">
            <div class="stat-card">
              <span class="stat-number">{{ animatedStats.articles }}+</span>
              <span class="stat-label">Articles Published</span>
            </div>
            <div class="stat-card">
              <span class="stat-number">{{ animatedStats.categories }}</span>
              <span class="stat-label">Categories</span>
            </div>
            <div class="stat-card">
              <span class="stat-number">{{ animatedStats.views }}+</span>
              <span class="stat-label">Total Views</span>
            </div>
            <div class="stat-card">
              <span class="stat-number">24/7</span>
              <span class="stat-label">Always Available</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- What We Offer Section -->
    <section class="features-section">
      <div class="container">
        <div class="section-header">
          <span class="section-label">What We Offer</span>
          <h2 class="section-title">Everything You Need to Learn & Grow</h2>
          <p class="section-subtitle">
            Discover a wealth of resources designed to help you become a better developer
          </p>
        </div>

        <div class="features-grid">
          <div class="feature-card" v-for="(feature, index) in features" :key="index">
            <div class="feature-icon">
              <component :is="feature.icon" />
            </div>
            <h3 class="feature-title">{{ feature.title }}</h3>
            <p class="feature-description">{{ feature.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Topics Section -->
    <section class="topics-section">
      <div class="container">
        <div class="section-header">
          <span class="section-label">Popular Topics</span>
          <h2 class="section-title">Explore Our Content Categories</h2>
          <p class="section-subtitle">
            From web development to DevOps, we cover the technologies that matter
          </p>
        </div>

        <div class="topics-grid">
          <div class="topic-card" v-for="(topic, index) in topics" :key="index">
            <div class="topic-icon">{{ topic.icon }}</div>
            <div class="topic-content">
              <h3 class="topic-title">{{ topic.name }}</h3>
              <p class="topic-description">{{ topic.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Values Section -->
    <section class="values-section">
      <div class="container">
        <div class="section-header">
          <span class="section-label">Our Values</span>
          <h2 class="section-title">What Drives Us</h2>
        </div>

        <div class="values-grid">
          <div class="value-card" v-for="(value, index) in values" :key="index">
            <div class="value-number">{{ String(index + 1).padStart(2, '0') }}</div>
            <h3 class="value-title">{{ value.title }}</h3>
            <p class="value-description">{{ value.description }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-content">
          <h2 class="cta-title">Ready to Start Learning?</h2>
          <p class="cta-description">
            Join our community and discover articles, tutorials, and insights from developers around the world.
          </p>
          <div class="cta-actions">
            <router-link to="/blog" class="btn btn-primary">
              Explore Articles
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </router-link>
            <router-link to="/" class="btn btn-secondary">
              Back to Home
            </router-link>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, h } from 'vue';
import http from '../services/http';

// Stats data
const stats = ref({
  articles: 0,
  categories: 0,
  views: 0,
});

// Animated stats for counting effect
const animatedStats = reactive({
  articles: '0',
  categories: '0',
  views: '0',
});

// Features data
const features = [
  {
    title: 'In-Depth Tutorials',
    description: 'Step-by-step guides that take you from beginner to expert with practical examples and real-world applications.',
    icon: {
      render() {
        return h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
          h('path', { d: 'M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z' }),
          h('path', { d: 'M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z' })
        ]);
      }
    }
  },
  {
    title: 'Latest Technologies',
    description: 'Stay up-to-date with articles covering the newest frameworks, tools, and best practices in software development.',
    icon: {
      render() {
        return h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
          h('circle', { cx: 12, cy: 12, r: 10 }),
          h('line', { x1: 2, y1: 12, x2: 22, y2: 12 }),
          h('path', { d: 'M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z' })
        ]);
      }
    }
  },
  {
    title: 'Expert Insights',
    description: 'Learn from experienced developers who share their knowledge, tips, and lessons learned from real projects.',
    icon: {
      render() {
        return h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
          h('path', { d: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z' })
        ]);
      }
    }
  },
  {
    title: 'Practical Examples',
    description: 'Every article includes code samples and examples you can use directly in your own projects.',
    icon: {
      render() {
        return h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
          h('polyline', { points: '16 18 22 12 16 6' }),
          h('polyline', { points: '8 6 2 12 8 18' })
        ]);
      }
    }
  },
  {
    title: 'Free Access',
    description: 'All our content is completely free. No paywalls, no subscriptions required. Knowledge should be accessible.',
    icon: {
      render() {
        return h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
          h('path', { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z' })
        ]);
      }
    }
  },
  {
    title: 'Regular Updates',
    description: 'New articles published regularly to keep you informed about the ever-evolving world of technology.',
    icon: {
      render() {
        return h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
          h('circle', { cx: 12, cy: 12, r: 10 }),
          h('polyline', { points: '12 6 12 12 16 14' })
        ]);
      }
    }
  }
];

// Topics data
const topics = [
  { icon: '🌐', name: 'Web Development', description: 'Frontend, backend, and full-stack development' },
  { icon: '📱', name: 'Mobile Development', description: 'iOS, Android, and cross-platform apps' },
  { icon: '☁️', name: 'Cloud & DevOps', description: 'AWS, Docker, Kubernetes, CI/CD' },
  { icon: '🗄️', name: 'Databases', description: 'SQL, NoSQL, and data management' },
  { icon: '🔐', name: 'Security', description: 'Best practices and secure coding' },
  { icon: '🤖', name: 'AI & Machine Learning', description: 'ML, AI, and data science' },
  { icon: '⚡', name: 'Performance', description: 'Optimization and best practices' },
  { icon: '🧪', name: 'Testing', description: 'Unit, integration, and E2E testing' }
];

// Values data
const values = [
  {
    title: 'Quality First',
    description: 'Every article is carefully crafted to provide accurate, up-to-date, and valuable information.'
  },
  {
    title: 'Community Driven',
    description: 'We believe in the power of shared knowledge and collaborative learning.'
  },
  {
    title: 'Accessibility',
    description: 'Making quality technical education available to everyone, regardless of background.'
  },
  {
    title: 'Continuous Learning',
    description: 'Technology evolves, and so do we. We\'re committed to staying current and relevant.'
  }
];

// Format number helper (for display with suffix: K, M, B, T)
const formatStatNumber = (num) => {
  if (!num || num <= 0) return '0';

  // Trillion (nghìn tỷ)
  if (num >= 1000000000000) {
    const value = num / 1000000000000;
    return (value >= 100 ? Math.round(value) : value.toFixed(1).replace(/\.0$/, '')) + 'T';
  }
  // Billion (tỷ)
  if (num >= 1000000000) {
    const value = num / 1000000000;
    return (value >= 100 ? Math.round(value) : value.toFixed(1).replace(/\.0$/, '')) + 'B';
  }
  // Million (triệu)
  if (num >= 1000000) {
    const value = num / 1000000;
    return (value >= 100 ? Math.round(value) : value.toFixed(1).replace(/\.0$/, '')) + 'M';
  }
  // Thousand (nghìn)
  if (num >= 1000) {
    const value = num / 1000;
    return (value >= 100 ? Math.round(value) : value.toFixed(1).replace(/\.0$/, '')) + 'K';
  }
  return num.toLocaleString();
};

// Animate counting effect
const animateCount = (key, targetValue, duration = 1500) => {
  const startTime = performance.now();
  const startValue = 0;

  const animate = (currentTime) => {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function (ease-out cubic)
    const easeOut = 1 - Math.pow(1 - progress, 3);

    const currentValue = Math.floor(startValue + (targetValue - startValue) * easeOut);
    animatedStats[key] = formatStatNumber(currentValue);

    if (progress < 1) {
      requestAnimationFrame(animate);
    } else {
      animatedStats[key] = formatStatNumber(targetValue);
    }
  };

  requestAnimationFrame(animate);
};

// Fetch stats
const fetchStats = async () => {
  try {
    const response = await http.get('/blog/stats');
    if (response.data.success) {
      const data = response.data.data;
      stats.value = {
        articles: data.posts || 0,
        categories: data.categories || 0,
        views: data.views || 0,
      };

      // Trigger counting animations
      animateCount('articles', data.posts || 0, 1200);
      animateCount('categories', data.categories || 0, 800);
      animateCount('views', data.views || 0, 1500);
    }
  } catch (error) {
    console.error('Failed to fetch stats:', error);
  }
};

onMounted(() => {
  fetchStats();
});
</script>

<style scoped>
.about-page {
  min-height: 100vh;
  background: #f8fafc;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero Section */
.about-hero {
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  padding: 80px 0;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.about-hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  opacity: 0.5;
}

.hero-content {
  position: relative;
  z-index: 1;
  max-width: 700px;
  margin: 0 auto;
}

.hero-title {
  font-size: clamp(2rem, 5vw, 3rem);
  font-weight: 700;
  color: #fff;
  margin-bottom: 16px;
  letter-spacing: -0.02em;
}

.hero-subtitle {
  font-size: clamp(1rem, 2vw, 1.25rem);
  color: rgba(255, 255, 255, 0.8);
  line-height: 1.6;
  margin: 0;
}

/* Section Common Styles */
.section-label {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #1a1a2e;
  background: #e5e7eb;
  padding: 6px 12px;
  border-radius: 50px;
  margin-bottom: 16px;
}

.section-title {
  font-size: clamp(1.5rem, 4vw, 2.25rem);
  font-weight: 700;
  color: #1a1a2e;
  margin-bottom: 12px;
  line-height: 1.2;
}

.section-subtitle {
  font-size: 1rem;
  color: #6b7280;
  max-width: 600px;
  margin: 0 auto;
  line-height: 1.6;
}

.section-header {
  text-align: center;
  margin-bottom: 48px;
}

.section-text {
  font-size: 1rem;
  color: #4b5563;
  line-height: 1.7;
  margin-bottom: 16px;
}

.section-text:last-child {
  margin-bottom: 0;
}

/* Mission Section */
.mission-section {
  padding: 80px 0;
  background: #fff;
}

.mission-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: center;
}

.mission-content .section-label {
  margin-bottom: 12px;
}

.mission-content .section-title {
  margin-bottom: 24px;
}

.mission-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.stat-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 2rem;
  font-weight: 700;
  color: #1a1a2e;
  margin-bottom: 4px;
}

.stat-card .stat-label {
  display: block;
  font-size: 0.875rem;
  color: #6b7280;
  background: none;
  padding: 0;
  text-transform: none;
  letter-spacing: normal;
}

/* Features Section */
.features-section {
  padding: 80px 0;
  background: #f8fafc;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.feature-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 28px;
  transition: all 0.2s;
}

.feature-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}

.feature-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1a1a2e;
  color: #fff;
  border-radius: 10px;
  margin-bottom: 20px;
}

.feature-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 10px;
}

.feature-description {
  font-size: 0.9375rem;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
}

/* Topics Section */
.topics-section {
  padding: 80px 0;
  background: #fff;
}

.topics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.topic-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 20px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  transition: all 0.2s;
}

.topic-card:hover {
  background: #fff;
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.topic-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.topic-title {
  font-size: 1rem;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 4px;
}

.topic-description {
  font-size: 0.8125rem;
  color: #6b7280;
  margin: 0;
  line-height: 1.4;
}

/* Values Section */
.values-section {
  padding: 80px 0;
  background: #f8fafc;
}

.values-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
}

.value-card {
  text-align: center;
  padding: 32px 24px;
}

.value-number {
  font-size: 2.5rem;
  font-weight: 700;
  color: #e5e7eb;
  margin-bottom: 16px;
  line-height: 1;
}

.value-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a1a2e;
  margin-bottom: 10px;
}

.value-description {
  font-size: 0.9375rem;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
}

/* CTA Section */
.cta-section {
  padding: 80px 0;
  background: #1a1a2e;
  position: relative;
  overflow: hidden;
}

.cta-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  opacity: 0.5;
}

.cta-content {
  position: relative;
  z-index: 1;
  text-align: center;
  max-width: 600px;
  margin: 0 auto;
}

.cta-title {
  font-size: clamp(1.5rem, 4vw, 2rem);
  font-weight: 700;
  color: #fff;
  margin-bottom: 12px;
}

.cta-description {
  font-size: 1.125rem;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 32px;
  line-height: 1.6;
}

.cta-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}

.btn-primary {
  background: #fff;
  color: #1a1a2e;
}

.btn-primary:hover {
  background: #f8fafc;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-secondary {
  background: transparent;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.5);
}

/* Responsive - Tablet Landscape */
@media (max-width: 1024px) {
  .mission-grid {
    gap: 40px;
  }

  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .topics-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .values-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Responsive - Tablet Portrait */
@media (max-width: 768px) {
  .container {
    padding: 0 1.25rem;
  }

  .about-hero {
    padding: 60px 0;
  }

  .hero-title {
    font-size: 1.75rem;
  }

  .hero-subtitle {
    font-size: 1rem;
  }

  .mission-section,
  .features-section,
  .topics-section,
  .values-section,
  .cta-section {
    padding: 60px 0;
  }

  .mission-grid {
    grid-template-columns: 1fr;
    gap: 40px;
  }

  .mission-stats {
    order: -1;
  }

  .section-header {
    margin-bottom: 36px;
  }

  .section-title {
    font-size: 1.5rem;
  }

  .features-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }

  .feature-card {
    padding: 24px;
  }

  .feature-icon {
    width: 44px;
    height: 44px;
    margin-bottom: 16px;
  }

  .topics-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .topic-card {
    padding: 16px;
  }

  .values-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .value-card {
    padding: 24px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
  }

  .value-number {
    font-size: 2rem;
    margin-bottom: 12px;
  }

  .cta-title {
    font-size: 1.5rem;
  }

  .cta-description {
    font-size: 1rem;
    margin-bottom: 24px;
  }

  .cta-actions {
    flex-direction: column;
    gap: 12px;
  }

  .btn {
    width: 100%;
    justify-content: center;
    padding: 12px 24px;
  }
}

/* Responsive - Mobile */
@media (max-width: 480px) {
  .container {
    padding: 0 1rem;
  }

  .about-hero {
    padding: 48px 0;
  }

  .hero-title {
    font-size: 1.5rem;
  }

  .hero-subtitle {
    font-size: 0.9375rem;
  }

  .mission-section,
  .features-section,
  .topics-section,
  .values-section,
  .cta-section {
    padding: 48px 0;
  }

  .section-label {
    font-size: 0.6875rem;
    padding: 5px 10px;
  }

  .section-title {
    font-size: 1.375rem;
  }

  .section-subtitle {
    font-size: 0.9375rem;
  }

  .section-text {
    font-size: 0.9375rem;
  }

  .mission-stats {
    gap: 12px;
  }

  .stat-card {
    padding: 20px 16px;
  }

  .stat-number {
    font-size: 1.5rem;
  }

  .stat-card .stat-label {
    font-size: 0.8125rem;
  }

  .feature-card {
    padding: 20px;
  }

  .feature-icon {
    width: 40px;
    height: 40px;
  }

  .feature-icon svg {
    width: 20px;
    height: 20px;
  }

  .feature-title {
    font-size: 1rem;
  }

  .feature-description {
    font-size: 0.875rem;
  }

  .topic-card {
    padding: 14px;
    gap: 12px;
  }

  .topic-icon {
    font-size: 1.25rem;
  }

  .topic-title {
    font-size: 0.9375rem;
  }

  .topic-description {
    font-size: 0.75rem;
  }

  .value-card {
    padding: 20px 16px;
  }

  .value-number {
    font-size: 1.75rem;
  }

  .value-title {
    font-size: 1rem;
  }

  .value-description {
    font-size: 0.875rem;
  }

  .cta-title {
    font-size: 1.375rem;
  }

  .cta-description {
    font-size: 0.9375rem;
  }

  .btn {
    padding: 12px 20px;
    font-size: 0.9375rem;
  }
}

/* Extra small screens */
@media (max-width: 360px) {
  .container {
    padding: 0 0.875rem;
  }

  .about-hero {
    padding: 40px 0;
  }

  .hero-title {
    font-size: 1.375rem;
  }

  .mission-section,
  .features-section,
  .topics-section,
  .values-section,
  .cta-section {
    padding: 40px 0;
  }

  .stat-card {
    padding: 16px 12px;
  }

  .stat-number {
    font-size: 1.375rem;
  }
}
</style>

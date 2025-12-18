<template>
  <AdminLayout>
    <div class="feedback-show">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        Loading...
      </div>

      <template v-else-if="feedback">
        <div class="page-header">
          <div>
            <router-link to="/admin/feedback" class="back-link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6" />
              </svg>
              Back to Feedback
            </router-link>
            <h1>{{ feedback.subject }}</h1>
          </div>
          <button @click="confirmDelete" class="btn btn-danger">
            Delete
          </button>
        </div>

        <div class="feedback-layout">
          <div class="feedback-main">
            <div class="feedback-card">
              <div class="feedback-header">
                <div class="user-info">
                  <div class="user-avatar">
                    {{ getInitials(feedback.user?.name || feedback.name || 'A') }}
                  </div>
                  <div>
                    <div class="user-name">{{ feedback.user?.name || feedback.name || 'Anonymous' }}</div>
                    <div class="user-email">{{ feedback.user?.email || feedback.email }}</div>
                  </div>
                </div>
                <div class="feedback-date">{{ formatDate(feedback.created_at) }}</div>
              </div>
              <div class="feedback-body">
                <p>{{ feedback.message }}</p>
              </div>
              <div v-if="feedback.url" class="feedback-url">
                <strong>Page URL:</strong>
                <a :href="feedback.url" target="_blank">{{ feedback.url }}</a>
              </div>
            </div>

            <div class="notes-section">
              <h3>Internal Notes</h3>
              <textarea
                v-model="notes"
                rows="4"
                placeholder="Add internal notes..."
              ></textarea>
              <button @click="saveNotes" class="btn btn-secondary" :disabled="savingNotes">
                {{ savingNotes ? 'Saving...' : 'Save Notes' }}
              </button>
            </div>
          </div>

          <div class="feedback-sidebar">
            <div class="sidebar-section">
              <h4>Status</h4>
              <select v-model="status" @change="updateStatus">
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>

            <div class="sidebar-section">
              <h4>Priority</h4>
              <select v-model="priority" @change="updatePriority">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>

            <div class="sidebar-section">
              <h4>Type</h4>
              <span class="badge badge-type">{{ feedback.type }}</span>
            </div>

            <div class="sidebar-section">
              <h4>Assignee</h4>
              <select v-model="assigneeId" @change="updateAssignee">
                <option value="">Unassigned</option>
                <!-- Would load from users -->
              </select>
            </div>

            <div class="sidebar-section">
              <h4>Created</h4>
              <span>{{ formatFullDate(feedback.created_at) }}</span>
            </div>

            <div v-if="feedback.updated_at !== feedback.created_at" class="sidebar-section">
              <h4>Updated</h4>
              <span>{{ formatFullDate(feedback.updated_at) }}</span>
            </div>
          </div>
        </div>
      </template>

      <ConfirmDialog
        v-if="showDeleteDialog"
        title="Delete Feedback"
        message="Delete this feedback permanently?"
        confirm-text="Delete"
        confirm-class="danger"
        @confirm="deleteFeedback"
        @cancel="showDeleteDialog = false"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../../components/layout/AdminLayout.vue';
import ConfirmDialog from '../../components/shared/ConfirmDialog.vue';
import { useFeedbackStore } from '../../stores/feedback';

const route = useRoute();
const router = useRouter();
const store = useFeedbackStore();

const notes = ref('');
const status = ref('pending');
const priority = ref('medium');
const assigneeId = ref('');
const savingNotes = ref(false);
const showDeleteDialog = ref(false);

const feedback = computed(() => store.currentFeedback);
const loading = computed(() => store.loading);

const getInitials = (name) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

const formatDate = (date) => {
  if (!date) return '';
  const now = new Date();
  const past = new Date(date);
  const diffDays = Math.floor((now.getTime() - past.getTime()) / 86400000);
  if (diffDays < 1) return 'Today';
  if (diffDays < 7) return `${diffDays} days ago`;
  return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatFullDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
};

const updateStatus = async () => {
  await store.updateStatus(route.params.id, status.value);
};

const updatePriority = async () => {
  await store.updatePriority(route.params.id, priority.value);
};

const updateAssignee = async () => {
  await store.assignFeedback(route.params.id, assigneeId.value || null);
};

const saveNotes = async () => {
  savingNotes.value = true;
  try {
    await store.addNotes(route.params.id, notes.value);
  } finally {
    savingNotes.value = false;
  }
};

const confirmDelete = () => {
  showDeleteDialog.value = true;
};

const deleteFeedback = async () => {
  await store.deleteFeedback(route.params.id);
  router.push('/admin/feedback');
};

watch(feedback, (newVal) => {
  if (newVal) {
    notes.value = newVal.admin_notes || '';
    status.value = newVal.status || 'pending';
    priority.value = newVal.priority || 'medium';
    assigneeId.value = newVal.assignee_id || '';
  }
});

onMounted(() => {
  store.fetchFeedbackItem(route.params.id);
});
</script>

<style scoped>
.feedback-show { max-width: 1000px; }
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 64px; }
.spinner { width: 32px; height: 32px; border: 3px solid #e5e7eb; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 16px; }
@keyframes spin { to { transform: rotate(360deg); } }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
.back-link { display: inline-flex; align-items: center; gap: 4px; color: #6b7280; text-decoration: none; font-size: 14px; margin-bottom: 8px; }
.back-link:hover { color: #4f46e5; }
.page-header h1 { font-size: 24px; font-weight: 700; color: #1f2937; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; }
.btn-danger { background: #ef4444; color: #fff; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.feedback-layout { display: grid; grid-template-columns: 1fr 280px; gap: 24px; }
.feedback-main { display: flex; flex-direction: column; gap: 24px; }
.feedback-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
.feedback-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb; }
.user-info { display: flex; gap: 12px; }
.user-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; }
.user-name { font-weight: 500; color: #1f2937; }
.user-email { font-size: 13px; color: #9ca3af; }
.feedback-date { font-size: 13px; color: #6b7280; }
.feedback-body p { margin: 0; color: #374151; line-height: 1.6; white-space: pre-wrap; }
.feedback-url { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 14px; }
.feedback-url a { color: #4f46e5; }
.notes-section { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
.notes-section h3 { font-size: 16px; font-weight: 600; margin: 0 0 12px 0; }
.notes-section textarea { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; margin-bottom: 12px; resize: vertical; }
.feedback-sidebar { display: flex; flex-direction: column; gap: 16px; }
.sidebar-section { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); }
.sidebar-section h4 { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin: 0 0 8px 0; }
.sidebar-section select { width: 100%; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.sidebar-section span { font-size: 14px; color: #374151; }
.badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; text-transform: capitalize; }
.badge-type { background: #dbeafe; color: #1e40af; }
@media (max-width: 768px) { .feedback-layout { grid-template-columns: 1fr; } }
</style>

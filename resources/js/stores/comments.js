/**
 * Comments Store
 *
 * Pinia store for managing comments state.
 */

import { defineStore } from 'pinia';
import { comments } from '../services/blog';

export const useCommentsStore = defineStore('comments', {
  state: () => ({
    // Comments for current post
    comments: [],
    commentsCount: 0,

    // Loading states
    loading: {
      fetch: false,
      create: false,
      reply: false,
      like: false,
    },

    // Error state
    error: null,

    // Reply form state
    replyingTo: null, // Comment ID being replied to
  }),

  getters: {
    isLoading: (state) => Object.values(state.loading).some(Boolean),

    // Get comment by ID
    getCommentById: (state) => (id) => {
      const findInComments = (commentsList) => {
        for (const item of commentsList) {
          if (item.comment.id === id) {
            return item;
          }
          if (item.replies && item.replies.length > 0) {
            const found = findInComments(item.replies);
            if (found) return found;
          }
        }
        return null;
      };
      return findInComments(state.comments);
    },
  },

  actions: {
    /**
     * Fetch comments for a post
     */
    async fetchComments(postId) {
      this.loading.fetch = true;
      this.error = null;

      try {
        const response = await comments.list(postId);
        const data = response.data;

        if (data.success) {
          this.comments = data.data.comments;
          this.commentsCount = data.data.count;
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch comments';
        console.error('Failed to fetch comments:', error);
      } finally {
        this.loading.fetch = false;
      }
    },

    /**
     * Create a new comment
     */
    async createComment(postId, commentData) {
      this.loading.create = true;
      this.error = null;

      try {
        const response = await comments.create(postId, commentData);
        const data = response.data;

        if (data.success) {
          // If comment is approved, add to list
          if (!data.data.pending_approval) {
            this.comments.unshift({
              comment: data.data.comment,
              replies: [],
            });
            this.commentsCount++;
          }

          return {
            success: true,
            message: data.message,
            pendingApproval: data.data.pending_approval,
          };
        }

        return { success: false, message: data.message };
      } catch (error) {
        const message = error.response?.data?.message || 'Failed to post comment';
        this.error = message;
        return { success: false, message };
      } finally {
        this.loading.create = false;
      }
    },

    /**
     * Reply to a comment
     */
    async replyToComment(commentId, replyData) {
      this.loading.reply = true;
      this.error = null;

      try {
        const response = await comments.reply(commentId, replyData);
        const data = response.data;

        if (data.success) {
          // If reply is approved, add to parent's replies
          if (!data.data.pending_approval) {
            this.addReplyToComment(commentId, {
              comment: data.data.comment,
              replies: [],
            });
            this.commentsCount++;
          }

          // Clear reply form state
          this.replyingTo = null;

          return {
            success: true,
            message: data.message,
            pendingApproval: data.data.pending_approval,
          };
        }

        return { success: false, message: data.message };
      } catch (error) {
        const message = error.response?.data?.message || 'Failed to post reply';
        this.error = message;
        return { success: false, message };
      } finally {
        this.loading.reply = false;
      }
    },

    /**
     * Like a comment
     */
    async likeComment(commentId) {
      this.loading.like = true;

      try {
        const response = await comments.like(commentId);
        const data = response.data;

        if (data.success) {
          // Update likes count in state
          this.incrementLikesCount(commentId);
          return { success: true };
        }

        return { success: false };
      } catch (error) {
        console.error('Failed to like comment:', error);
        return { success: false };
      } finally {
        this.loading.like = false;
      }
    },

    /**
     * Helper to add reply to a comment (recursive)
     */
    addReplyToComment(parentId, reply) {
      const addReply = (commentsList) => {
        for (const item of commentsList) {
          if (item.comment.id === parentId) {
            item.replies.push(reply);
            return true;
          }
          if (item.replies && item.replies.length > 0) {
            if (addReply(item.replies)) return true;
          }
        }
        return false;
      };

      addReply(this.comments);
    },

    /**
     * Helper to increment likes count (recursive)
     */
    incrementLikesCount(commentId) {
      const increment = (commentsList) => {
        for (const item of commentsList) {
          if (item.comment.id === commentId) {
            item.comment.likes_count = (item.comment.likes_count || 0) + 1;
            return true;
          }
          if (item.replies && item.replies.length > 0) {
            if (increment(item.replies)) return true;
          }
        }
        return false;
      };

      increment(this.comments);
    },

    /**
     * Set comment being replied to
     */
    setReplyingTo(commentId) {
      this.replyingTo = commentId;
    },

    /**
     * Cancel reply
     */
    cancelReply() {
      this.replyingTo = null;
    },

    /**
     * Clear comments
     */
    clearComments() {
      this.comments = [];
      this.commentsCount = 0;
      this.replyingTo = null;
      this.error = null;
    },
  },
});

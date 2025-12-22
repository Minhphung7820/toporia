/**
 * Content formatting utilities for comments
 * Handles code blocks, text segments, and HTML entity decoding
 */

/**
 * Decode HTML entities (e.g., &lt; -> <, &amp; -> &)
 * @param {string} text - Text with HTML entities
 * @returns {string} Decoded text
 */
export const decodeHtmlEntities = (text) => {
  if (!text) return '';
  const textarea = document.createElement('textarea');
  textarea.innerHTML = text;
  return textarea.value;
};

/**
 * Escape HTML special characters for safe display
 * @param {string} text - Raw text
 * @returns {string} Escaped text
 */
export const escapeHtml = (text) => {
  if (!text) return '';
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
};

/**
 * Strip HTML tags but preserve PHP/XML tags like <?php
 * Only removes tags starting with < followed by letter or /
 * @param {string} text - HTML text
 * @returns {string} Text with HTML tags stripped
 */
export const stripHtmlTags = (text) => {
  if (!text) return '';
  return text.replace(/<\/?[a-zA-Z][^>]*>/g, '');
};

/**
 * Extract code content from a <pre> block
 * Uses robust position-based extraction instead of regex
 * @param {string} preContent - Content inside <pre> tag
 * @returns {string} Extracted code content
 */
export const extractCodeContent = (preContent) => {
  if (!preContent) return '';

  // Find <code> tag positions
  const codeOpenMatch = preContent.match(/<code[^>]*>/i);
  const codeCloseIdx = preContent.lastIndexOf('</code>');

  if (codeOpenMatch && codeCloseIdx !== -1) {
    // Extract content between <code> and </code>
    const codeStartIdx = codeOpenMatch.index + codeOpenMatch[0].length;
    return preContent.slice(codeStartIdx, codeCloseIdx);
  }

  // No <code> tag, return pre content directly
  return preContent;
};

/**
 * Format comment content with code blocks and text segments
 * Preserves original order and handles PHP/special tags correctly
 *
 * @param {string} content - Raw comment content (may contain HTML entities)
 * @param {Object} options - Formatting options
 * @param {boolean} options.decode - Whether to decode HTML entities first (default: true for admin, false for public)
 * @param {boolean} options.escapeCode - Whether to escape code content (default: true for admin, false for public)
 * @returns {string} Formatted HTML string
 */
export const formatCommentContent = (content, options = {}) => {
  if (!content) return '';

  const { decode = false, escapeCode = false } = options;

  // Decode HTML entities if needed (admin pages store encoded content)
  let processedContent = decode ? decodeHtmlEntities(content) : content;

  const result = [];
  const preRegex = /<pre[^>]*>([\s\S]*?)<\/pre>/gi;
  let lastIndex = 0;
  let match;

  while ((match = preRegex.exec(processedContent)) !== null) {
    // Get text before this code block
    const textBefore = processedContent.slice(lastIndex, match.index);
    const cleanText = stripHtmlTags(textBefore).trim();
    if (cleanText) {
      result.push(`<div class="text-segment">${cleanText}</div>`);
    }

    // Extract code content
    const preContent = match[1];
    let codeContent = extractCodeContent(preContent);

    // Only add if there's actual code content
    if (codeContent.trim()) {
      // Escape if needed (admin needs escaping after decode, public doesn't)
      const finalCode = escapeCode ? escapeHtml(codeContent) : codeContent;
      result.push(`<pre class="code-block"><code>${finalCode}</code></pre>`);
    }

    lastIndex = match.index + match[0].length;
  }

  // Get any remaining text after the last code block
  const remainingText = processedContent.slice(lastIndex);
  const cleanRemaining = stripHtmlTags(remainingText).trim();
  if (cleanRemaining) {
    result.push(`<div class="text-segment">${cleanRemaining}</div>`);
  }

  // If no structured parts found, try plain text
  if (result.length === 0) {
    const plainText = stripHtmlTags(processedContent).trim();
    if (plainText) {
      return `<div class="text-segment">${plainText}</div>`;
    }
    return '';
  }

  return result.join('');
};

/**
 * Format mentions in content
 * @param {string} content - Content with mentions
 * @param {Array} mentions - Array of mention objects with name/username
 * @returns {string} Content with styled mentions
 */
export const formatMentions = (content, mentions = []) => {
  if (!content) return '';

  let result = content;

  // Style existing mention spans
  result = result.replace(
    /<span class="mention"[^>]*>@([^<]+)<\/span>/g,
    '<span class="mention-tag">@$1</span>'
  );

  // Handle plain @mentions from backend
  if (mentions && mentions.length > 0) {
    mentions.forEach(mention => {
      const name = mention.name || mention.username;
      if (name) {
        // Avoid matching inside HTML tags
        const regex = new RegExp(`@${name}(?![^<]*>)`, 'g');
        result = result.replace(regex, `<span class="mention-tag">@${name}</span>`);
      }
    });
  }

  return result;
};

/**
 * Get content parts for table preview (admin)
 * Returns array of { type: 'text' | 'code', content: string }
 * @param {string} content - Raw comment content
 * @param {number} maxTextLength - Max length for text preview (default: 80)
 * @param {number} maxCodeLines - Max lines for code preview (default: 2)
 * @returns {Array} Array of content parts
 */
export const getContentParts = (content, maxTextLength = 80, maxCodeLines = 2) => {
  if (!content) return [];

  const decoded = decodeHtmlEntities(content);
  const parts = [];

  const preRegex = /<pre[^>]*>[\s\S]*?<\/pre>/gi;
  let lastIndex = 0;
  let match;

  while ((match = preRegex.exec(decoded)) !== null) {
    // Get text before this code block
    const textBefore = decoded.slice(lastIndex, match.index);
    const cleanText = stripHtmlTags(textBefore).replace(/\s+/g, ' ').trim();
    if (cleanText) {
      const truncated = cleanText.length > maxTextLength
        ? cleanText.slice(0, maxTextLength) + '...'
        : cleanText;
      parts.push({ type: 'text', content: truncated });
    }

    // Extract code content
    const preContent = match[0].replace(/<\/?pre[^>]*>/gi, '');
    let code = extractCodeContent(preContent);

    if (code) {
      // Get first N lines for preview
      const lines = code.split('\n').slice(0, maxCodeLines);
      let preview = lines.join('\n');
      if (preview.length > 100) {
        preview = preview.slice(0, 100);
      }
      parts.push({
        type: 'code',
        content: preview + (code.length > preview.length ? '...' : '')
      });
    }

    lastIndex = match.index + match[0].length;
  }

  // Get any remaining text
  const remainingText = decoded.slice(lastIndex);
  const cleanRemaining = stripHtmlTags(remainingText).replace(/\s+/g, ' ').trim();
  if (cleanRemaining) {
    const truncated = cleanRemaining.length > maxTextLength
      ? cleanRemaining.slice(0, maxTextLength) + '...'
      : cleanRemaining;
    parts.push({ type: 'text', content: truncated });
  }

  // If no parts found, try plain text
  if (parts.length === 0) {
    const plainText = stripHtmlTags(decoded).replace(/\s+/g, ' ').trim();
    if (plainText) {
      const truncated = plainText.length > maxTextLength
        ? plainText.slice(0, maxTextLength) + '...'
        : plainText;
      parts.push({ type: 'text', content: truncated });
    }
  }

  return parts;
};

/**
 * Check if content has code blocks
 * @param {string} content - Comment content
 * @returns {boolean} True if content contains code blocks
 */
export const hasCodeBlock = (content) => {
  if (!content) return false;
  return content.includes('<pre') || content.includes('&lt;pre');
};

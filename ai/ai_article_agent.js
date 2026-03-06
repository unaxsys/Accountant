#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const OPENAI_API_KEY = process.env.OPENAI_API_KEY || '';
const OPENAI_TEXT_MODEL = process.env.OPENAI_TEXT_MODEL || 'gpt-4.1-mini';
const OPENAI_IMAGE_MODEL = process.env.OPENAI_IMAGE_MODEL || 'gpt-image-1';
const CMS_BASE_URL = (process.env.CMS_BASE_URL || 'http://localhost').replace(/\/$/, '');
const AI_PUBLISH_SECRET = process.env.AI_PUBLISH_SECRET || '';
const LOG_FILE = path.join(__dirname, 'logs', 'ai_agent.log');
const UPLOADS_DIR = path.join(__dirname, '..', 'uploads', 'blog');

function slugify(input) {
  const map = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y',
    'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
    'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sht', 'ъ': 'a', 'ь': '', 'ю': 'yu', 'я': 'ya'
  };

  const transliterated = String(input || '')
    .trim()
    .toLowerCase()
    .split('')
    .map((char) => map[char] ?? char)
    .join('');

  return transliterated
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'statia';
}

function logStatus(title, status, extra = '') {
  fs.mkdirSync(path.dirname(LOG_FILE), { recursive: true });
  const line = `[${new Date().toISOString()}] title="${title}" status="${status}" ${extra}`.trim();
  fs.appendFileSync(LOG_FILE, `${line}\n`, 'utf8');
}

async function openAiJsonResponse(prompt) {
  const response = await fetch('https://api.openai.com/v1/chat/completions', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${OPENAI_API_KEY}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      model: OPENAI_TEXT_MODEL,
      temperature: 0.7,
      response_format: { type: 'json_object' },
      messages: [
        {
          role: 'system',
          content: 'You are an expert Bulgarian accounting and tax writer. Always answer with factual, current and practical information only.'
        },
        {
          role: 'user',
          content: prompt
        }
      ]
    })
  });

  if (!response.ok) {
    const errText = await response.text();
    throw new Error(`OpenAI text request failed: ${response.status} ${errText}`);
  }

  const data = await response.json();
  const raw = data?.choices?.[0]?.message?.content;
  if (!raw) {
    throw new Error('OpenAI text response missing content');
  }

  return JSON.parse(raw);
}

async function generateArticle() {
  const payload = await openAiJsonResponse(
    [
      'Generate one SEO blog article in Bulgarian for an accounting firm website.',
      'Topic must be accounting or tax related and factually accurate.',
      'Return strict JSON with keys: title, category, meta_description, excerpt, tags, content_html, image_prompt.',
      'content_html must be valid HTML, minimum 1000 words, contain multiple <h2> headings, and include actionable practical guidance.',
      'tags must be a comma-separated Bulgarian list.',
      'meta_description max 160 chars.',
      'excerpt max 280 chars.'
    ].join(' ')
  );

  const title = String(payload.title || '').trim();
  if (!title) {
    throw new Error('Generated article title is empty');
  }

  return {
    title,
    slug: slugify(title),
    category: String(payload.category || 'schetovodstvo').trim(),
    meta: String(payload.meta_description || '').trim(),
    excerpt: String(payload.excerpt || '').trim(),
    tags: String(payload.tags || '').trim(),
    content: String(payload.content_html || '').trim(),
    imagePrompt: String(payload.image_prompt || `Professional blog cover image for article: ${title}`).trim()
  };
}

async function fetchExistingArticleLinks() {
  try {
    const response = await fetch(`${CMS_BASE_URL}/blog`);
    if (!response.ok) {
      return [];
    }

    const html = await response.text();
    const links = new Set();
    const regex = /href=["'](\/blog\/[^"'#?]+)["']/gi;
    let match;
    while ((match = regex.exec(html)) !== null) {
      const link = match[1];
      const slug = link.replace('/blog/', '');
      const anchorText = slug.replace(/-/g, ' ');
      links.add(JSON.stringify({ href: link, anchorText }));
    }

    return Array.from(links).map((item) => JSON.parse(item));
  } catch (_) {
    return [];
  }
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function addInternalLinks(content, existingLinks) {
  let updated = content;
  for (const item of existingLinks.slice(0, 5)) {
    const anchorText = item.anchorText.trim();
    if (anchorText.length < 12) {
      continue;
    }

    if (updated.includes(`href="${item.href}"`) || updated.includes(`href='${item.href}'`)) {
      continue;
    }

    const reg = new RegExp(`(>[^<]*)\\b(${escapeRegExp(anchorText)})\\b([^<]*<)`, 'i');
    if (reg.test(updated)) {
      updated = updated.replace(reg, `$1<a href="${item.href}">$2</a>$3`);
    }
  }
  return updated;
}

async function generateImage(imagePrompt, slug) {
  fs.mkdirSync(UPLOADS_DIR, { recursive: true });

  const response = await fetch('https://api.openai.com/v1/images/generations', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${OPENAI_API_KEY}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      model: OPENAI_IMAGE_MODEL,
      prompt: `${imagePrompt}. Clean editorial style, no readable text in image.`,
      size: '1536x1024'
    })
  });

  if (!response.ok) {
    const errText = await response.text();
    throw new Error(`OpenAI image request failed: ${response.status} ${errText}`);
  }

  const data = await response.json();
  const b64 = data?.data?.[0]?.b64_json;
  if (!b64) {
    throw new Error('OpenAI image response missing b64 image data');
  }

  const fileName = `${Date.now()}-${slug}.png`;
  const fullPath = path.join(UPLOADS_DIR, fileName);
  fs.writeFileSync(fullPath, Buffer.from(b64, 'base64'));

  return `/uploads/blog/${fileName}`;
}

async function publishArticle(article) {
  const response = await fetch(`${CMS_BASE_URL}/admin/api/ai_publish_article.php`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-AI-SECRET': AI_PUBLISH_SECRET
    },
    body: JSON.stringify(article)
  });

  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.ok) {
    throw new Error(`Publish failed (${response.status}): ${JSON.stringify(payload)}`);
  }

  return payload;
}

async function main() {
  if (!OPENAI_API_KEY) {
    throw new Error('OPENAI_API_KEY is required');
  }

  if (!AI_PUBLISH_SECRET) {
    throw new Error('AI_PUBLISH_SECRET is required');
  }

  const article = await generateArticle();
  const existingLinks = await fetchExistingArticleLinks();
  article.content = addInternalLinks(article.content, existingLinks);
  article.cover = await generateImage(article.imagePrompt, article.slug);
  delete article.imagePrompt;

  const published = await publishArticle(article);
  logStatus(article.title, 'published', `slug="${published.slug}" id="${published.id}"`);
  process.stdout.write(`Published article ID ${published.id} with slug ${published.slug}\n`);
}

main().catch((error) => {
  logStatus('unknown', 'error', `message="${error.message.replace(/"/g, '\\"')}"`);
  process.stderr.write(`${error.stack || error.message}\n`);
  process.exitCode = 1;
});

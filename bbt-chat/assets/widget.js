/**
 * BBT chat widget.
 *
 * Everything lives inside a shadow root. The BBT pages carry large blocks of
 * inline CSS with broad selectors, and each page is a standalone document; a
 * shadow root is the only way to guarantee the widget cannot restyle the site
 * and the site cannot restyle the widget. That matters more than a few bytes
 * here, because this is being added to a live site that already works.
 */
(function () {
  'use strict';

  var config = window.BBT_CHAT_CONFIG || {};
  if (!config.endpoint) {
    return;
  }

  var MAX_QUESTION = 500;

  var STYLES = [
    ':host { all: initial; }',
    '* { box-sizing: border-box; font-family: "IBM Plex Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }',

    '.launcher { position: fixed; right: max(24px, env(safe-area-inset-right));',
    '  bottom: calc(92px + env(safe-area-inset-bottom)); z-index: 2147483000;',
    '  width: 56px; height: 56px; border-radius: 50%; border: 2px solid transparent;',
    '  background: #13294B; color: #fff; cursor: pointer; display: inline-flex;',
    '  align-items: center; justify-content: center;',
    '  box-shadow: 0 18px 48px rgba(13,27,42,.28); transition: transform .18s ease; }',
    '.launcher:hover { transform: translateY(-2px); }',
    '.launcher:focus-visible { outline: 3px solid #F5A623; outline-offset: 3px; }',
    '.launcher svg { width: 26px; height: 26px; }',

    '.panel { position: fixed; right: max(24px, env(safe-area-inset-right));',
    '  bottom: calc(92px + env(safe-area-inset-bottom)); z-index: 2147483001;',
    '  width: min(390px, calc(100vw - 32px)); height: min(560px, calc(100vh - 140px));',
    '  background: #fff; border: 1px solid #DCE3EC; border-radius: 16px;',
    '  box-shadow: 0 28px 70px rgba(13,27,42,.30); display: flex; flex-direction: column;',
    '  overflow: hidden; }',
    '@media (max-width: 480px) {',
    '  .panel { right: 8px; left: 8px; width: auto; bottom: 8px; height: min(76vh, 560px); }',
    '}',

    '.header { background: linear-gradient(135deg,#0F2547 0%,#13294B 100%); color: #fff;',
    '  padding: 14px 16px; display: flex; align-items: center; gap: 10px; }',
    '.header h2 { margin: 0; font-size: 15px; font-weight: 600; letter-spacing: .01em; }',
    '.header p { margin: 2px 0 0; font-size: 11px; color: #8A99A8;',
    '  font-family: "IBM Plex Mono", ui-monospace, monospace; letter-spacing: .1em; text-transform: uppercase; }',
    '.header .close { margin-left: auto; background: none; border: 0; color: #fff; cursor: pointer;',
    '  font-size: 22px; line-height: 1; padding: 4px 6px; border-radius: 6px; }',
    '.header .close:hover { background: rgba(255,255,255,.14); }',

    '.log { flex: 1; overflow-y: auto; padding: 16px; background: #F4F6F9; display: flex;',
    '  flex-direction: column; gap: 12px; }',
    '.msg { max-width: 88%; padding: 10px 13px; border-radius: 12px; font-size: 14px; line-height: 1.55;',
    '  white-space: pre-wrap; overflow-wrap: anywhere; }',
    '.msg.bot { background: #fff; border: 1px solid #DCE3EC; color: #0D1B2A; align-self: flex-start;',
    '  border-bottom-left-radius: 4px; }',
    '.msg.user { background: #13294B; color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }',
    '.msg.error { background: #FCEFD4; border: 1px solid #F5A623; color: #0D1B2A; align-self: flex-start; }',
    '.msg a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }',

    '.sources { align-self: flex-start; max-width: 88%; display: flex; flex-wrap: wrap; gap: 6px; }',
    '.sources a { font-size: 11px; padding: 4px 9px; border-radius: 999px; background: #EEF2F7;',
    '  border: 1px solid #DCE3EC; color: #475569; text-decoration: none; }',
    '.sources a:hover { background: #fff; color: #13294B; }',

    '.starters { display: flex; flex-wrap: wrap; gap: 6px; }',
    '.starters button { font-size: 12px; padding: 7px 11px; border-radius: 999px; cursor: pointer;',
    '  background: #fff; border: 1px solid #DCE3EC; color: #13294B; }',
    '.starters button:hover { border-color: #13294B; }',

    '.typing { align-self: flex-start; display: flex; gap: 4px; padding: 12px 14px; background: #fff;',
    '  border: 1px solid #DCE3EC; border-radius: 12px; }',
    '.typing span { width: 6px; height: 6px; border-radius: 50%; background: #8A99A8;',
    '  animation: blink 1.2s infinite ease-in-out; }',
    '.typing span:nth-child(2) { animation-delay: .18s; }',
    '.typing span:nth-child(3) { animation-delay: .36s; }',
    '@keyframes blink { 0%, 80%, 100% { opacity: .25; } 40% { opacity: 1; } }',
    '@media (prefers-reduced-motion: reduce) { .typing span { animation: none; opacity: .5; }',
    '  .launcher { transition: none; } }',

    '.composer { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #DCE3EC; background: #fff; }',
    '.composer textarea { flex: 1; resize: none; border: 1px solid #DCE3EC; border-radius: 10px;',
    '  padding: 9px 11px; font-size: 14px; line-height: 1.4; max-height: 96px; color: #0D1B2A; }',
    '.composer textarea:focus { outline: 2px solid #2563EB; outline-offset: -1px; }',
    '.composer button { border: 0; border-radius: 10px; background: #F5A623; color: #0D1B2A;',
    '  font-weight: 600; font-size: 13px; padding: 0 16px; cursor: pointer; }',
    '.composer button:disabled { opacity: .5; cursor: default; }',

    '.legal { padding: 0 12px 10px; background: #fff; font-size: 10.5px; color: #8A99A8; text-align: center; }',
    '[hidden] { display: none !important; }',
  ].join('\n');

  var host = document.createElement('div');
  host.setAttribute('data-bbt-chat', '');
  var root = host.attachShadow({ mode: 'open' });
  var style = document.createElement('style');
  style.textContent = STYLES;
  root.appendChild(style);

  var launcher = document.createElement('button');
  launcher.className = 'launcher';
  launcher.type = 'button';
  launcher.setAttribute('aria-label', 'Ask BBT a question');
  launcher.innerHTML =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
    'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.5 9.5 0 0 1-3.2-.5L3 21l1.7-4.6A8.2 8.2 0 0 1 3.6 11.5 8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>';

  var panel = document.createElement('div');
  panel.className = 'panel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', 'Chat with Big Binary Trainings');
  panel.hidden = true;
  panel.innerHTML =
    '<div class="header">' +
    '  <div><h2>' + esc(config.title || 'Ask BBT') + '</h2><p>Usually answers instantly</p></div>' +
    '  <button class="close" type="button" aria-label="Close chat">&times;</button>' +
    '</div>' +
    '<div class="log" role="log" aria-live="polite" aria-atomic="false"></div>' +
    '<form class="composer">' +
    '  <textarea rows="1" maxlength="' + MAX_QUESTION + '" placeholder="Ask about courses, timings, admission…" ' +
    '    aria-label="Your question"></textarea>' +
    '  <button type="submit">Send</button>' +
    '</form>' +
    '<p class="legal">AI assistant. For fees and admission, please confirm with our team.</p>';

  root.appendChild(launcher);
  root.appendChild(panel);
  document.body.appendChild(host);

  var log = panel.querySelector('.log');
  var form = panel.querySelector('.composer');
  var input = panel.querySelector('textarea');
  var sendButton = panel.querySelector('.composer button');
  var history = [];
  var busy = false;

  function esc(value) {
    return String(value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** Escapes first, then linkifies. Model output is never inserted as raw HTML. */
  function renderText(text) {
    return esc(text).replace(/(https?:\/\/[^\s<>()]+[^\s<>().,;:!?])/g, function (url) {
      return '<a href="' + url + '" target="_blank" rel="noopener nofollow">' + url + '</a>';
    });
  }

  function addMessage(role, text) {
    var node = document.createElement('div');
    node.className = 'msg ' + role;
    node.innerHTML = renderText(text);
    log.appendChild(node);
    log.scrollTop = log.scrollHeight;
    return node;
  }

  function addSources(sources) {
    if (!sources || !sources.length) {
      return;
    }
    var wrap = document.createElement('div');
    wrap.className = 'sources';
    sources.slice(0, 3).forEach(function (source) {
      var link = document.createElement('a');
      link.href = source.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = shortLabel(source);
      wrap.appendChild(link);
    });
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }

  function shortLabel(source) {
    var path = String(source.url || '').replace(/^https?:\/\/[^/]+/, '').replace(/\//g, ' ').trim();
    return path === '' ? 'Home' : path.replace(/-/g, ' ');
  }

  function showStarters() {
    var starters = config.starters || [
      'What courses do you offer?',
      'Do you have classes for kids?',
      'Where are you located?',
      'How do I enroll?',
    ];
    var wrap = document.createElement('div');
    wrap.className = 'starters';
    starters.forEach(function (question) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = question;
      button.addEventListener('click', function () {
        wrap.remove();
        send(question);
      });
      wrap.appendChild(button);
    });
    log.appendChild(wrap);
  }

  function setBusy(state) {
    busy = state;
    sendButton.disabled = state;
    input.disabled = state;
  }

  function send(question) {
    question = String(question || '').trim();
    if (question === '' || busy) {
      return;
    }
    addMessage('user', question);
    input.value = '';
    input.style.height = 'auto';
    setBusy(true);

    var typing = document.createElement('div');
    typing.className = 'typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    log.appendChild(typing);
    log.scrollTop = log.scrollHeight;

    var headers = { 'Content-Type': 'application/json' };
    if (config.nonce) {
      headers['X-WP-Nonce'] = config.nonce;
    }

    fetch(config.endpoint, {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: JSON.stringify({ question: question, history: history.slice(-6) }),
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, status: response.status, data: data };
        });
      })
      .then(function (result) {
        typing.remove();
        if (!result.ok || !result.data || !result.data.answer) {
          var message = (result.data && (result.data.message || result.data.error))
            || 'Something went wrong. Please try again, or reach us on WhatsApp at 0326-0188811.';
          addMessage('error', message);
          return;
        }
        addMessage('bot', result.data.answer);
        addSources(result.data.sources);
        history.push({ role: 'user', content: question });
        history.push({ role: 'assistant', content: result.data.answer });
      })
      .catch(function () {
        typing.remove();
        addMessage('error', 'Could not reach the assistant. Please check your connection, or message us on WhatsApp at 0326-0188811.');
      })
      .then(function () {
        setBusy(false);
        input.focus();
      });
  }

  function open() {
    panel.hidden = false;
    launcher.hidden = true;
    if (log.childElementCount === 0) {
      addMessage('bot', config.greeting
        || 'Hello. I can answer questions about BBT courses, tracks, timings and admission. What would you like to know?');
      showStarters();
    }
    input.focus();
  }

  function close() {
    panel.hidden = true;
    launcher.hidden = false;
    launcher.focus();
  }

  launcher.addEventListener('click', open);
  panel.querySelector('.close').addEventListener('click', close);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !panel.hidden) {
      close();
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    send(input.value);
  });

  input.addEventListener('input', function () {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 96) + 'px';
  });

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      send(input.value);
    }
  });
})();

(function(window, document) {
    if (!window.ScoreConfig || !ScoreConfig.siteId || !ScoreConfig.endpoint) {
      console.warn('Score tracker not configured.');
      return;
    }
  
    // --- Session Handling ---
    const getSessionId = () => {
      const key = 'score_session_id';
      let sid = localStorage.getItem(key);
      if (!sid) {
        sid = (crypto && crypto.randomUUID)
          ? crypto.randomUUID()
          : 'sess-' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem(key, sid);
      }
      return sid;
    };
    const sessionId = getSessionId();
  
    // --- UTM & Referrer ---
    const urlParams = new URLSearchParams(window.location.search);
    const utm = {};
    ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(k => {
      if (urlParams.has(k)) utm[k] = urlParams.get(k);
    });
    const referrer = document.referrer;
  
    // --- Base Payload ---
    const basePayload = {
      siteId: ScoreConfig.siteId,
      sessionId,
      timestamp: new Date().toISOString(),
      url: window.location.href,
      referrer,
      utm,
      userAgent: navigator.userAgent,
      ...(ScoreConfig.userInfo || {})
    };
  
    const sendEvent = (type, details = {}) => {
      const payload = { ...basePayload, type, details };
      const body = JSON.stringify(payload);
      if (navigator.sendBeacon) {
        navigator.sendBeacon(ScoreConfig.endpoint, body);
      } else {
        fetch(ScoreConfig.endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body,
        });
      }
    };
  
    // --- Track Pageview ---
    sendEvent('pageview');
  
    // --- Click Events ---
    document.addEventListener('click', (e) => {
      let el = e.target;
      while (el && el !== document) {
        const ev = el.getAttribute('data-score-event');
        if (ev) {
          sendEvent('click', { eventName: ev, text: el.innerText.trim() });
          break;
        }
        el = el.parentNode;
      }
    });
  
    // --- Form Submissions ---
    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (form.matches && form.matches('form')) {
        sendEvent('form_submit', {
          formId: form.id || null,
          formName: form.name || null
        });
      }
    }, true);
  
    // --- Scroll Depth ---
    let scrollFlags = {};
    const trackScroll = () => {
      const percent = Math.round((window.scrollY + window.innerHeight) / document.body.scrollHeight * 100);
      [25, 50, 75, 100].forEach(p => {
        if (percent >= p && !scrollFlags[p]) {
          scrollFlags[p] = true;
          sendEvent('scroll', { depth: p });
        }
      });
    };
    window.addEventListener('scroll', trackScroll);
  
    // --- Video Events ---
    document.querySelectorAll('video').forEach(video => {
      ['play', 'pause', 'ended'].forEach(evt => {
        video.addEventListener(evt, () => {
          sendEvent('video_' + evt, { src: video.currentSrc });
        });
      });
    });
  
  })(window, document);
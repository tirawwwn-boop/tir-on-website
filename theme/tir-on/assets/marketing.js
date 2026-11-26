(() => {
  'use strict';
  const panel = document.querySelector('#tiron-marketing');
  const cfg = window.tironMarketing;
  if (!panel || !cfg) return;
  const cookie = key => document.cookie.split('; ').find(value => value.startsWith(key + '='))?.split('=')[1];
  const write = (key, value, age) => { document.cookie = key + '=' + value + '; Max-Age=' + age + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); };
  const blocked = navigator.globalPrivacyControl === true;
  function script(src) {
    const el = document.createElement('script');
    el.src = src; el.async = true; el.referrerPolicy = 'no-referrer';
    document.head.append(el);
  }
  function syncConsent() {
    if ((blocked || cookie('tiron_marketing') !== 'yes') && cfg.catalog && (window.fbq || window.ttq)) {
      window.fbq?.('consent','revoke');
      window.ttq?.revokeConsent?.();
      location.reload();
    } else apply();
  }
  window.addEventListener('storage', event => { if (event.key === 'tiron-consent-change') syncConsent(); });
  window.addEventListener('pageshow', event => { if (event.persisted) syncConsent(); });
  const active = !blocked && cookie('tiron_marketing') === 'yes';
  panel.querySelector('[role=status]').textContent = active ? 'Măsurare permisă.' : 'Măsurare oprită.';
  panel.querySelector('[data-choice=yes]').disabled = blocked;
  panel.addEventListener('click', e => {
    const choice = e.target.dataset.choice;
    if (!['yes','no'].includes(choice) || (blocked && choice === 'yes')) return;
    if (choice === 'yes' && !cookie('tiron_ad_id')) {
      const id = [...crypto.getRandomValues(new Uint8Array(16))].map(n => n.toString(16).padStart(2,'0')).join('');
      write('tiron_ad_id', id, 15552000);
    }
    write('tiron_marketing', choice, 15552000);
    if (choice === 'no') {
      write('tiron_ad_id', '', 0);
    }
    try { localStorage.setItem('tiron-consent-change', String(Date.now())); } catch { /* Cookie remains authoritative. */ }
    // Do not interrupt an in-progress checkout, or discard the selected local photo.
    syncConsent();
  });
  let started = false;
  function apply() {
    const allowed = !blocked && cookie('tiron_marketing') === 'yes';
    panel.querySelector('[role=status]').textContent = allowed ? 'Măsurare permisă.' : 'Măsurare oprită.';
    if (!allowed || started) return;
    started = true;
    const receipt = document.querySelector('#tiron-conversion');
    if (receipt) {
      fetch(receipt.dataset.url, {method:'POST', credentials:'same-origin', referrerPolicy:'no-referrer',
        body:new URLSearchParams({id:receipt.dataset.id, proof:receipt.dataset.proof})}).catch(() => {});
    }
    // Only load vendor libraries on public catalogue URLs, without query strings or a private referrer.
    if (!cfg.catalog || location.search || location.hash || (document.referrer && new URL(document.referrer).search)) return;
    if (cfg.ids.meta) {
      const fbq = window.fbq = function() { if (fbq.callMethod) fbq.callMethod.apply(fbq,arguments); else fbq.queue.push(arguments); };
      fbq.queue=[]; fbq.push=fbq; fbq.loaded=true; fbq.version='2.0';
      fbq('set','autoConfig',false,cfg.ids.meta); fbq('init',cfg.ids.meta); fbq('track','PageView');
      script('https://connect.facebook.net/en_US/fbevents.js');
    }
    if (cfg.ids.tiktok) {
      window.TiktokAnalyticsObject='ttq';
      const q=window.ttq=window.ttq || [];
      q.methods = ['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie','holdConsent','revokeConsent','grantConsent'];
      q.setAndDefer = (queue, name) => { queue[name] = (...args) => queue.push([name,...args]); };
      q.methods.forEach(name => q.setAndDefer(q, name));
      q.instance = id => {
        const queue = q._i[id] || [];
        q.methods.forEach(name => q.setAndDefer(queue, name));
        return queue;
      };
      q._i=q._i || {}; q._i[cfg.ids.tiktok]=[]; q._i[cfg.ids.tiktok]._u='https://analytics.tiktok.com/i18n/pixel/events.js';
      q._t=q._t || {}; q._t[cfg.ids.tiktok]=Date.now(); q._o=q._o || {}; q._o[cfg.ids.tiktok]={};
      q.grantConsent(); q.page();
      script(q._i[cfg.ids.tiktok]._u+'?sdkid='+encodeURIComponent(cfg.ids.tiktok)+'&lib=ttq');
    }
  }
  apply();
})();

(function () {
  var cfg = window.wvwConfig || {};
  var root = (cfg.root || '/wp-json/').replace(/\/$/, '/');
  var interval = (cfg.interval || 300) * 1000;

  function url(path, params) {
    var q = Object.keys(params)
      .filter(function (k) { return params[k] !== '' && params[k] != null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
      .join('&');
    return root + 'wvw/v1/' + path + (q ? '?' + q : '');
  }

  function updateValues(el, payload) {
    // score / ppt / skirmish: .wvw-value[data-team], keyed by container type
    el.querySelectorAll('.wvw-value[data-team]').forEach(function (node) {
      var team = node.getAttribute('data-team');
      var type = el.getAttribute('data-wvw-type');
      var key = (type === 'score') ? 'scores' : type;
      var map = payload[key];
      if (map && map[team] != null) { node.textContent = Number(map[team]).toLocaleString(); }
    });
    // kills widget: [data-team][data-field] where field is a payload key (kills/deaths/kdr)
    el.querySelectorAll('[data-team][data-field]').forEach(function (node) {
      var team = node.getAttribute('data-team');
      var field = node.getAttribute('data-field');
      if (payload[field] && payload[field][team] != null) {
        node.textContent = Number(payload[field][team]).toLocaleString();
      }
    });
    // objectives: [data-team][data-type] where type is a structure type
    el.querySelectorAll('[data-team][data-type]').forEach(function (node) {
      var team = node.getAttribute('data-team');
      var type = node.getAttribute('data-type');
      if (payload.objectives && payload.objectives[team]) {
        node.textContent = payload.objectives[team][type];
      }
    });
  }

  function refreshMatch(el) {
    var params = { team: el.getAttribute('data-wvw-team'), match: el.getAttribute('data-wvw-match') };
    fetch(url('match', params))
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (p) { if (p && !p.error) { updateValues(el, p); } })
      .catch(function () {});
  }

  function tick() {
    document.querySelectorAll('.wvw-container[data-wvw-type]').forEach(function (el) {
      if (el.getAttribute('data-wvw-type') !== 'standings') { refreshMatch(el); }
    });
  }

  if (document.querySelector('.wvw-container')) {
    setInterval(tick, interval);
  }
})();

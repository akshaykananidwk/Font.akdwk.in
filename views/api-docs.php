<?php defined('BASE_PATH') or die('Direct access denied'); ?>
<div class="container">
  <nav class="breadcrumbs"><a href="<?= Helper::e(App::url('/')) ?>">હોમ</a> <span>›</span> <span>API</span></nav>
  <h1>Developer API</h1>
  <p class="intro">તમારી app, વેબસાઇટ કે workflow માં ફોન્ટ કન્વર્ઝન integrate કરો. API access માટે <a href="<?= Helper::e(App::url('/pricing')) ?>">Pro/Business plan</a> જરૂરી છે — key તમારા <a href="<?= Helper::e(App::url('/dashboard')) ?>">ડેશબોર્ડ</a> માંથી બનાવો.</p>

  <section class="api-section">
    <h2>Endpoint: Convert</h2>
    <pre class="code-block">POST <?= Helper::e(App::url('/api/v1/convert')) ?></pre>
    <h3>Headers</h3>
    <pre class="code-block">X-API-Key: your_api_key
Content-Type: application/json</pre>
    <h3>Request Body</h3>
    <pre class="code-block">{
  "text": "કન્વર્ટ કરવાનો ટેક્સ્ટ",
  "font": "lmg",
  "direction": "legacy_to_unicode"
}</pre>
    <h3>Response (200)</h3>
    <pre class="code-block">{
  "success": true,
  "data": {
    "converted_text": "...",
    "char_count": 25,
    "font": "LMG Arun",
    "direction": "legacy_to_unicode",
    "processing_time_ms": 12
  },
  "usage": { "calls_today": 45, "daily_limit": 1000, "remaining": 955 }
}</pre>
    <h3>Error Codes</h3>
    <div class="table-wrap"><table class="data-table">
      <tr><th>Code</th><th>અર્થ</th></tr>
      <tr><td>400</td><td>Bad request — text/font/direction ખૂટે છે કે અમાન્ય</td></tr>
      <tr><td>401</td><td>Invalid API key</td></tr>
      <tr><td>403</td><td>Key suspended / IP not allowed / plan expired</td></tr>
      <tr><td>429</td><td>Rate limit — <code>Retry-After</code> header જુઓ</td></tr>
      <tr><td>500</td><td>Server error</td></tr>
    </table></div>
  </section>

  <section class="api-section">
    <h2>અન્ય Endpoints</h2>
    <pre class="code-block">GET  <?= Helper::e(App::url('/api/v1/fonts')) ?>     — ઉપલબ્ધ ફોન્ટની યાદી
GET  <?= Helper::e(App::url('/api/v1/usage')) ?>     — તમારો આજનો usage
POST <?= Helper::e(App::url('/api/v1/batch')) ?>     — એકસાથે અનેક texts (Business plan)</pre>
  </section>

  <section class="api-section">
    <h2>Code Examples</h2>
    <h3>cURL</h3>
    <pre class="code-block">curl -X POST '<?= Helper::e(App::url('/api/v1/convert')) ?>' \
  -H 'X-API-Key: YOUR_KEY' \
  -H 'Content-Type: application/json' \
  -d '{"text":"kml","font":"lmg","direction":"legacy_to_unicode"}'</pre>
    <h3>PHP</h3>
    <pre class="code-block">$ch = curl_init('<?= Helper::e(App::url('/api/v1/convert')) ?>');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['X-API-Key: YOUR_KEY', 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'text' => 'kml', 'font' => 'lmg', 'direction' => 'legacy_to_unicode',
    ]),
]);
$result = json_decode(curl_exec($ch), true);
echo $result['data']['converted_text'];</pre>
    <h3>Python</h3>
    <pre class="code-block">import requests

r = requests.post('<?= Helper::e(App::url('/api/v1/convert')) ?>',
    headers={'X-API-Key': 'YOUR_KEY'},
    json={'text': 'kml', 'font': 'lmg', 'direction': 'legacy_to_unicode'})
print(r.json()['data']['converted_text'])</pre>
    <h3>JavaScript (fetch)</h3>
    <pre class="code-block">const res = await fetch('<?= Helper::e(App::url('/api/v1/convert')) ?>', {
  method: 'POST',
  headers: { 'X-API-Key': 'YOUR_KEY', 'Content-Type': 'application/json' },
  body: JSON.stringify({ text: 'kml', font: 'lmg', direction: 'legacy_to_unicode' })
});
const json = await res.json();
console.log(json.data.converted_text);</pre>
    <h3>Node.js</h3>
    <pre class="code-block">const res = await fetch('<?= Helper::e(App::url('/api/v1/convert')) ?>', {
  method: 'POST',
  headers: { 'X-API-Key': process.env.GFC_KEY, 'Content-Type': 'application/json' },
  body: JSON.stringify({ text: 'kml', font: 'lmg', direction: 'legacy_to_unicode' }),
});
console.log((await res.json()).data.converted_text);</pre>
  </section>

  <section class="api-section">
    <h2>Try It — Live Console</h2>
    <div class="api-console">
      <label>API Key</label>
      <input type="text" id="tryKey" placeholder="gfc_...">
      <label>Text</label>
      <textarea id="tryText" rows="3">kml</textarea>
      <div class="form-row">
        <div><label>Font slug</label><input type="text" id="tryFont" value="lmg"></div>
        <div><label>Direction</label>
          <select id="tryDirection">
            <option value="legacy_to_unicode">legacy_to_unicode</option>
            <option value="unicode_to_legacy">unicode_to_legacy</option>
          </select>
        </div>
      </div>
      <button class="btn" id="tryBtn">▶ Try it</button>
      <pre class="code-block" id="tryResult">// response અહીં આવશે</pre>
    </div>
  </section>
</div>
<script>
document.getElementById('tryBtn')?.addEventListener('click', async () => {
  const out = document.getElementById('tryResult');
  out.textContent = '⏳ ...';
  try {
    const res = await fetch('<?= Helper::e(App::url('/api/v1/convert')) ?>', {
      method: 'POST',
      headers: {
        'X-API-Key': document.getElementById('tryKey').value.trim(),
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        text: document.getElementById('tryText').value,
        font: document.getElementById('tryFont').value.trim(),
        direction: document.getElementById('tryDirection').value
      })
    });
    out.textContent = JSON.stringify(await res.json(), null, 2);
  } catch (e) {
    out.textContent = 'Request failed: ' + e.message;
  }
});
</script>

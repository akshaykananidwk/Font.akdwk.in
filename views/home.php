<?php
defined('BASE_PATH') or die('Direct access denied');
/**
 * મુખ્ય કન્વર્ટર પેજ — home + language landing + font-wise pages બધા આ view વાપરે છે.
 */
$e = fn($s) => Helper::e((string)$s);
$fonts = $fonts ?? [];
$popularFonts = $popularFonts ?? [];
$selectedSlug = $selectedSlug ?? '';
?>
<div class="container">
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="<?= $e(App::url('/')) ?>">હોમ</a>
    <?php if (!empty($fontPageData)): ?>
      <span>›</span> <span><?= $e($fontPageData['font_name']) ?> કન્વર્ટર</span>
    <?php endif; ?>
  </nav>

  <h1><?= $e($h1 ?? 'ગુજરાતી ફોન્ટ કન્વર્ટર') ?></h1>
  <p class="intro"><?= $e($introText ?? '') ?></p>

  <section class="converter-card" aria-label="ફોન્ટ કન્વર્ટર">
    <?php // hidden — JS ને દરેક box નો font slug + direction આપે છે ?>
    <input type="hidden" id="fontSlug" value="<?= $e($selectedSlug ?: 'lmg') ?>">

    <?php /* ---- Box 1 (ઉપર): Non-Unicode / Legacy font ---- */ ?>
    <div class="conv-box">
      <div class="conv-box-head">
        <div class="box-selector font-select-wrap">
          <span class="box-lang">ફોન્ટ:</span>
          <input type="text" id="fontSearch" placeholder="ફોન્ટ પસંદ કરો... (દા.ત. LMG)" autocomplete="off"
                 aria-label="ફોન્ટ પસંદ કરો" value="">
          <div class="font-dropdown" id="fontDropdown" role="listbox">
            <?php if ($popularFonts): ?>
            <div class="fd-group">⭐ લોકપ્રિય</div>
            <?php foreach ($popularFonts as $f): ?>
              <div class="fd-item" role="option" data-slug="<?= $e($f['font_slug']) ?>" data-name="<?= $e($f['font_name']) ?>"><?= $e($f['font_name']) ?></div>
            <?php endforeach; ?>
            <?php endif; ?>
            <div class="fd-group">બધા ફોન્ટ</div>
            <?php foreach ($fonts as $f): ?>
              <div class="fd-item" role="option" data-slug="<?= $e($f['font_slug']) ?>" data-name="<?= $e($f['font_name']) ?>"><?= $e($f['font_name']) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <span class="char-counter" id="counterLegacy">0 અક્ષર</span>
      </div>
      <textarea id="legacyText" dir="ltr" spellcheck="false"
        placeholder="અહીં જૂના (legacy) ફોન્ટનો ટેક્સ્ટ લખો કે paste કરો..." aria-label="Legacy ટેક્સ્ટ"></textarea>
      <div class="conv-actions">
        <button class="btn-sm" data-copy="legacyText">📋 કૉપી</button>
        <button class="btn-sm" data-clear="legacyText">✖ ક્લિયર</button>
      </div>
    </div>

    <?php /* ---- વચ્ચે: બે દિશાના કન્વર્ટ બટન ---- */ ?>
    <div class="convert-btns-mid">
      <button class="btn conv-dir" id="btnToUnicode" title="ફોન્ટ → Unicode">↓ Unicode માં કન્વર્ટ</button>
      <button class="btn conv-dir btn-secondary" id="btnToLegacy" title="Unicode → ફોન્ટ">↑ ફોન્ટમાં કન્વર્ટ</button>
    </div>

    <?php /* ---- Box 2 (નીચે): Unicode (Shruti) ---- */ ?>
    <div class="conv-box">
      <div class="conv-box-head">
        <div class="box-selector">
          <span class="box-lang">ગુજરાતી</span>
          <span class="fmt-fixed">Unicode (Shruti)</span>
        </div>
        <span class="char-counter" id="counterUnicode">0 અક્ષર</span>
      </div>
      <textarea id="unicodeText" dir="ltr" spellcheck="false" lang="gu"
        placeholder="Unicode પરિણામ અહીં આવશે... (તમારા મોબાઈલમાં Unicode ટાઇપ કરીને અહીં paste પણ કરી શકો)" aria-label="Unicode ટેક્સ્ટ"></textarea>
      <div class="conv-actions">
        <button class="btn-sm" data-copy="unicodeText">📋 કૉપી</button>
        <button class="btn-sm" data-clear="unicodeText">✖ ક્લિયર</button>
      </div>
    </div>

    <div class="demo-bar" id="demoBar" hidden>
      <span id="demoBarText"></span>
      <a href="<?= $e(App::url('/pricing')) ?>" class="upgrade-link">અમર્યાદિત માટે પ્લાન જુઓ →</a>
    </div>
    <div class="conv-status" id="convStatus" role="status" aria-live="polite"></div>
  </section>

  <?php if ($popularFonts): ?>
  <section class="chips-section">
    <h2 class="h-small">લોકપ્રિય ફોન્ટ</h2>
    <div class="chips">
      <?php foreach ($popularFonts as $f): ?>
        <a class="chip" href="<?= $e(App::url('/' . $f['font_slug'] . '-to-unicode-converter')) ?>"><?= $e($f['font_name']) ?></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($fontPageData)): ?>
  <section class="seo-content">
    <?php if (!empty($fontPageData['page_content'])): ?>
      <?= $fontPageData['page_content'] /* admin-edited trusted HTML */ ?>
    <?php else: ?>
      <h2><?= $e($fontPageData['font_name']) ?> ફોન્ટ શું છે?</h2>
      <p><?= $e($fontPageData['font_name']) ?> એ <?= $e($fontPageData['lang_name']) ?> ભાષામાં ટાઇપિંગ માટે વર્ષોથી વપરાતો legacy (non-Unicode) ફોન્ટ છે. જૂનાં અખબારો, પ્રેસ, DTP કામ અને સરકારી કચેરીઓમાં આ ફોન્ટમાં હજારો દસ્તાવેજ બનેલા છે. પરંતુ legacy ફોન્ટનો ટેક્સ્ટ WhatsApp, વેબસાઇટ કે Google માં બરાબર દેખાતો નથી — એ માટે Unicode (<?= $e($fontPageData['unicode_font']) ?>) જરૂરી છે.</p>
      <h2><?= $e($fontPageData['font_name']) ?> થી Unicode કન્વર્ટ કરવાનાં પગલાં</h2>
      <ol>
        <li>તમારા દસ્તાવેજ (Word, PageMaker, CorelDRAW વગેરે) માંથી <?= $e($fontPageData['font_name']) ?> નો ટેક્સ્ટ કૉપી કરો.</li>
        <li>ઉપરના ડાબા બોક્સ (Non-Unicode) માં paste કરો.</li>
        <li>"Unicode માં કન્વર્ટ →" બટન દબાવો.</li>
        <li>જમણા બોક્સમાંથી Unicode ટેક્સ્ટ કૉપી કરો અથવા .txt ફાઇલ ડાઉનલોડ કરો.</li>
      </ol>
      <h2>સામાન્ય સમસ્યાઓ અને ઉકેલ</h2>
      <ul>
        <li><strong>અક્ષરો ઊંધા-ચત્તા દેખાય:</strong> ખાતરી કરો કે તમે સાચો ફોન્ટ પસંદ કર્યો છે — <?= $e($fontPageData['font_name']) ?> ના અલગ-અલગ variants અલગ mapping વાપરે છે.</li>
        <li><strong>િ માત્રા ખોટી જગ્યાએ:</strong> અમારું એન્જિન િ માત્રા આપમેળે સાચી જગ્યાએ મૂકે છે; છતાં ભૂલ દેખાય તો સંપર્ક પેજથી જણાવો.</li>
        <li><strong>જોડાક્ષર તૂટે છે:</strong> ટેક્સ્ટ paste કરતી વખતે formatting સાફ થઈ ગઈ હોઈ શકે — plain text paste કરો.</li>
      </ul>
      <h2>FAQ — <?= $e($fontPageData['font_name']) ?></h2>
      <div class="faq-item"><h3><?= $e($fontPageData['font_name']) ?> થી Unicode કન્વર્ઝન મફત છે?</h3><p>હા, 200 અક્ષર સુધી સંપૂર્ણ મફત. મોટા દસ્તાવેજ માટે સસ્તા plans છે.</p></div>
      <div class="faq-item"><h3>કન્વર્ઝન કેટલું સચોટ છે?</h3><p>માત્રા-reordering અને જોડાક્ષર સહિતના નિયમો એન્જિનમાં છે; છતાં મહત્વના દસ્તાવેજ હંમેશા ચકાસી લેવા.</p></div>
      <div class="faq-item"><h3>Unicode માંથી પાછું <?= $e($fontPageData['font_name']) ?> માં થાય?</h3><p>હા — "← Non-Unicode માં કન્વર્ટ" બટનથી ઊલટું કન્વર્ઝન પણ થાય છે.</p></div>
      <div class="faq-item"><h3>મારો ટેક્સ્ટ સુરક્ષિત છે?</h3><p>હા — ટેક્સ્ટ ક્યારેય સર્વર પર સ્ટોર થતો નથી.</p></div>
    <?php endif; ?>
    <?php if (!empty($relatedFonts)): ?>
    <h2 class="h-small">સંબંધિત ફોન્ટ કન્વર્ટર</h2>
    <div class="chips">
      <?php foreach ($relatedFonts as $rf): ?>
        <a class="chip" href="<?= $e(App::url('/' . $rf['font_slug'] . '-to-unicode-converter')) ?>"><?= $e($rf['font_name']) ?> → Unicode</a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section class="seo-content">
    <h2>આ ટૂલ કેવી રીતે વાપરવું?</h2>
    <ol>
      <li>ઉપરના ડ્રોપડાઉનમાંથી તમારો legacy ફોન્ટ પસંદ કરો (દા.ત. LMG, Shree Guj, Saral).</li>
      <li>ડાબા બોક્સમાં જૂના ફોન્ટનો ટેક્સ્ટ paste કરો.</li>
      <li>"Unicode માં કન્વર્ટ" દબાવો — પરિણામ તરત જમણા બોક્સમાં આવશે.</li>
      <li>કૉપી કરો કે .txt ડાઉનલોડ કરો. Unicode → Legacy પણ એ જ રીતે થાય.</li>
    </ol>
    <h2>Legacy ફોન્ટ vs Unicode — ફરક શું?</h2>
    <p>જૂના (legacy) ફોન્ટ જેમ કે LMG કે Shree Guj માં ગુજરાતી અક્ષરો English keyboard ના ASCII codes પર "ચોંટાડેલા" હોય છે — એટલે ફોન્ટ install ન હોય તો ટેક્સ્ટ કચરો દેખાય. Unicode (Shruti, Nirmala UI) એ આંતરરાષ્ટ્રીય ધોરણ છે: દરેક ગુજરાતી અક્ષરનો પોતાનો કાયમી કોડ છે, એટલે ટેક્સ્ટ દરેક ફોન, કમ્પ્યુટર અને વેબસાઇટ પર બરાબર દેખાય છે, Google માં શોધાય છે અને હંમેશ માટે સચવાય છે.</p>
    <h2 id="faq">વારંવાર પૂછાતા પ્રશ્નો</h2>
    <div class="faq-item"><h3>આ ટૂલ મફત છે?</h3><p>હા — 200 અક્ષર સુધીનું demo સંપૂર્ણ મફત છે. અમર્યાદિત ઉપયોગ અને API માટે plans જુઓ.</p></div>
    <div class="faq-item"><h3>મારો ટેક્સ્ટ સર્વર પર સ્ટોર થાય છે?</h3><p>ના. ટેક્સ્ટ ક્યારેય સ્ટોર થતો નથી — ફક્ત character count આંકડા માટે નોંધાય છે.</p></div>
    <div class="faq-item"><h3>કયા ફોન્ટ સપોર્ટ થાય છે?</h3><p>ગુજરાતી, હિન્દી, મરાઠી અને નેપાળી મળીને 90+ legacy ફોન્ટ. યાદી ડ્રોપડાઉનમાં જુઓ.</p></div>
    <div class="faq-item"><h3>Word/Excel ફાઇલ સીધી કન્વર્ટ થાય?</h3><p>હાલ plain text (paste અથવા .txt ફાઇલ). Paid plan માં .txt ફાઇલ અપલોડ ઉપલબ્ધ છે.</p></div>
  </section>
  <?php endif; ?>
</div>

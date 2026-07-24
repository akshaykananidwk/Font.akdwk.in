<?php
defined('BASE_PATH') or die('Direct access denied');
/**
 * Main converter page — used by home + language landing + font-wise pages.
 */
$e = fn($s) => Helper::e((string)$s);
$fonts = $fonts ?? [];
$popularFonts = $popularFonts ?? [];
$selectedSlug = $selectedSlug ?? '';
?>
<div class="container">
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="<?= $e(App::url('/')) ?>">Home</a>
    <?php if (!empty($fontPageData)): ?>
      <span>›</span> <span><?= $e($fontPageData['font_name']) ?> Converter</span>
    <?php endif; ?>
  </nav>

  <h1><?= $e($h1 ?? 'Gujarati Font Converter') ?></h1>
  <p class="intro"><?= $e($introText ?? '') ?></p>

  <section class="converter-card" aria-label="Font Converter">
    <?php
    /* Both boxes get an identical searchable picker: "Unicode" + every legacy font.
       Convert direction is decided automatically from the two selections
       (exactly one side must be Unicode, the other a legacy font). */
    $renderFmtItems = function () use ($e, $popularFonts, $fonts) {
        echo '<div class="fd-group">Format</div>';
        echo '<div class="fd-item" role="option" data-slug="unicode" data-name="Unicode (Shruti)">Unicode (Shruti)</div>';
        if ($popularFonts) {
            echo '<div class="fd-group">⭐ Popular fonts</div>';
            foreach ($popularFonts as $f) {
                printf('<div class="fd-item" role="option" data-slug="%s" data-name="%s">%s</div>',
                    $e($f['font_slug']), $e($f['font_name']), $e($f['font_name']));
            }
        }
        echo '<div class="fd-group">All fonts</div>';
        foreach ($fonts as $f) {
            printf('<div class="fd-item" role="option" data-slug="%s" data-name="%s">%s</div>',
                $e($f['font_slug']), $e($f['font_name']), $e($f['font_name']));
        }
    };
    ?>

    <?php /* ---- Box 1 (top): defaults to the legacy font ---- */ ?>
    <div class="conv-box">
      <div class="conv-box-head">
        <div class="box-selector fmt-picker">
          <span class="box-lang">From:</span>
          <input type="text" class="fmt-search" id="searchTop" placeholder="Select font / Unicode..." autocomplete="off" aria-label="Top box format" value="">
          <input type="hidden" class="fmt-value" id="fmtTop" value="<?= $e($selectedSlug ?: 'lmg') ?>">
          <div class="font-dropdown" id="dropdownTop" role="listbox"><?php $renderFmtItems(); ?></div>
        </div>
        <span class="char-counter" id="counterTop">0 characters</span>
      </div>
      <textarea id="boxTop" dir="ltr" spellcheck="false"
        placeholder="Type or paste your text here..." aria-label="Top text"></textarea>
      <div class="conv-actions">
        <button class="btn-sm" data-copy="boxTop">📋 Copy</button>
        <button class="btn-sm" data-clear="boxTop">✖ Clear</button>
      </div>
    </div>

    <?php /* ---- Between: two directional convert buttons ---- */ ?>
    <div class="convert-btns-mid">
      <button class="btn conv-dir" id="btnDown" title="Convert top box to bottom box">↓ Convert</button>
      <button class="btn conv-dir btn-secondary" id="btnUp" title="Convert bottom box to top box">↑ Convert</button>
    </div>

    <?php /* ---- Box 2 (bottom): defaults to Unicode ---- */ ?>
    <div class="conv-box">
      <div class="conv-box-head">
        <div class="box-selector fmt-picker">
          <span class="box-lang">To:</span>
          <input type="text" class="fmt-search" id="searchBottom" placeholder="Select font / Unicode..." autocomplete="off" aria-label="Bottom box format" value="">
          <input type="hidden" class="fmt-value" id="fmtBottom" value="unicode">
          <div class="font-dropdown" id="dropdownBottom" role="listbox"><?php $renderFmtItems(); ?></div>
        </div>
        <span class="char-counter" id="counterBottom">0 characters</span>
      </div>
      <textarea id="boxBottom" dir="ltr" spellcheck="false" lang="gu"
        placeholder="Result appears here... (you can also type Unicode on your phone and paste it here)" aria-label="Bottom text"></textarea>
      <div class="conv-actions">
        <button class="btn-sm" data-copy="boxBottom">📋 Copy</button>
        <button class="btn-sm" data-clear="boxBottom">✖ Clear</button>
      </div>
    </div>

    <div class="demo-bar" id="demoBar" hidden>
      <span id="demoBarText"></span>
      <a href="<?= $e(App::url('/pricing')) ?>" class="upgrade-link">See plans for unlimited use →</a>
    </div>
    <div class="conv-status" id="convStatus" role="status" aria-live="polite"></div>
  </section>

  <?php if ($popularFonts): ?>
  <section class="chips-section">
    <h2 class="h-small">Popular Fonts</h2>
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
      <h2>What is the <?= $e($fontPageData['font_name']) ?> font?</h2>
      <p><?= $e($fontPageData['font_name']) ?> is a legacy (non-Unicode) font that has been used for years to type <?= $e($fontPageData['lang_name']) ?>. Thousands of documents in old newspapers, print media, DTP work and government offices were created in this font. However, legacy-font text does not display correctly on WhatsApp, websites or Google — for that you need Unicode (<?= $e($fontPageData['unicode_font']) ?>).</p>
      <h2>Steps to convert <?= $e($fontPageData['font_name']) ?> to Unicode</h2>
      <ol>
        <li>Copy the <?= $e($fontPageData['font_name']) ?> text from your document (Word, PageMaker, CorelDRAW, etc.).</li>
        <li>Paste it into the top box (Non-Unicode).</li>
        <li>Press the "↓ Convert to Unicode" button.</li>
        <li>Copy the Unicode text from the lower box and use it anywhere.</li>
      </ol>
      <h2>Common problems and solutions</h2>
      <ul>
        <li><strong>Characters look jumbled:</strong> Make sure you selected the correct font — different variants of <?= $e($fontPageData['font_name']) ?> use different mappings.</li>
        <li><strong>The "i" matra is in the wrong place:</strong> Our engine places the "i" matra correctly automatically; if you still see an error, please report it via the contact page.</li>
        <li><strong>Conjuncts break apart:</strong> Formatting may have been lost while pasting — paste as plain text.</li>
      </ul>
      <h2>FAQ — <?= $e($fontPageData['font_name']) ?></h2>
      <div class="faq-item"><h3>Is <?= $e($fontPageData['font_name']) ?> to Unicode conversion free?</h3><p>Yes, up to 200 characters is completely free. Affordable plans are available for larger documents.</p></div>
      <div class="faq-item"><h3>How accurate is the conversion?</h3><p>Rules for matra-reordering and conjuncts are built into the engine; even so, always verify important documents.</p></div>
      <div class="faq-item"><h3>Can I convert Unicode back to <?= $e($fontPageData['font_name']) ?>?</h3><p>Yes — the "↑ Convert to Font" button performs the reverse conversion too.</p></div>
      <div class="faq-item"><h3>Is my text safe?</h3><p>Yes — your text is never stored on the server.</p></div>
    <?php endif; ?>
    <?php if (!empty($relatedFonts)): ?>
    <h2 class="h-small">Related font converters</h2>
    <div class="chips">
      <?php foreach ($relatedFonts as $rf): ?>
        <a class="chip" href="<?= $e(App::url('/' . $rf['font_slug'] . '-to-unicode-converter')) ?>"><?= $e($rf['font_name']) ?> → Unicode</a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section class="seo-content">
    <h2>How to use this tool</h2>
    <ol>
      <li>Select your legacy font from the dropdown above (e.g. LMG, Shree Guj, Saral).</li>
      <li>Paste the old font's text into the top box.</li>
      <li>Press "↓ Convert to Unicode" — the result appears instantly in the lower box.</li>
      <li>Copy it, or use the reverse direction. Unicode → Legacy works the same way.</li>
    </ol>
    <h2>Legacy font vs Unicode — what's the difference?</h2>
    <p>In legacy fonts such as LMG or Shree Guj, the Gujarati letters are "pasted" onto the ASCII codes of an English keyboard — so if the font isn't installed, the text looks like garbage. Unicode (Shruti, Nirmala UI) is the international standard: every Gujarati letter has its own permanent code, so the text displays correctly on every phone, computer and website, is searchable on Google, and is preserved forever.</p>
    <h2 id="faq">Frequently Asked Questions</h2>
    <div class="faq-item"><h3>Is this tool free?</h3><p>Yes — the demo is completely free up to 200 characters. See our plans for unlimited use and API access.</p></div>
    <div class="faq-item"><h3>Is my text stored on the server?</h3><p>No. The text is never stored — only the character count is recorded for statistics.</p></div>
    <div class="faq-item"><h3>Which fonts are supported?</h3><p>90+ legacy fonts across Gujarati, Hindi, Marathi and Nepali. See the list in the dropdown.</p></div>
    <div class="faq-item"><h3>Can I convert Word/Excel files directly?</h3><p>Currently plain text (paste or .txt file). File upload is available on paid plans.</p></div>
  </section>
  <?php endif; ?>
</div>

<?php
/** WooCommerce product, gallery, and private photo personalization form. */
$luna_name = $product_data['name'];
$luna_images = array_values(array_filter($product_data['images']));
$luna_count = count($luna_images);
$luna_limit = (int) $product_data['limit_mb'];
$luna_min = (int) $product_data['minimum'];
$luna_max = max($luna_min, (int) $product_data['maximum']);
$luna_available = $product_data['available'];
$luna_dimensions = $product_data['photo_dimensions'];
$luna_draft = $luna_available && function_exists('tiron_take_photo_draft') ? tiron_take_photo_draft() : [];
$luna_qty = min($luna_max, max($luna_min, (int) ($luna_draft['quantity'] ?? $luna_min)));
$luna_icon = static function ($name) {
    $paths = array(
        'zoom'   => '<circle cx="10" cy="10" r="6.2"/><path d="m15 15 5 5M7.5 10h5M10 7.5v5"/>',
        'minus'  => '<path d="M5 12h14"/>',
        'plus'   => '<path d="M5 12h14M12 5v14"/>',
    );
    return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>';
};
?>
<main id="continut" class="luna-product" data-luna-product
      data-name="<?php echo esc_attr($luna_name); ?>" data-limit="<?php echo $luna_limit; ?>"
      data-in-stock="<?php echo $luna_available ? 'true' : 'false'; ?>"
      data-max-side="<?php echo (int) ($luna_dimensions['max_side'] ?? 0); ?>"
      data-max-pixels="<?php echo (int) ($luna_dimensions['max_pixels'] ?? 0); ?>">
  <div class="lp-wrap">
    <nav class="lp-breadcrumb" aria-label="Parcurs de navigare">
      <a href="<?php echo t_e(t_url('home')); ?>">Acasă</a>
      <span aria-hidden="true">/</span><span aria-current="page"><?php echo t_e($luna_name); ?></span>
    </nav>
    <div class="lp-layout">
      <section class="lp-gallery" aria-label="Galerie foto produs">
        <?php if ($luna_available) : ?>
          <div class="lp-mobile-buy" data-mobile-buy>
            <div><span><?php echo t_e($luna_name); ?></span><strong><?php echo t_e($product_data['price']); ?></strong></div>
            <a class="button-gold" href="#personalizare">Personalizează Luna <?php echo t_icon('down', 16); ?></a>
          </div>
        <?php endif; ?>
        <div class="lp-media">
          <?php if ($luna_count) : ?>
            <button type="button" class="lp-photo-open" aria-label="Mărește imaginea produsului" aria-haspopup="dialog" data-photo-open>
              <img src="<?php echo t_e($luna_images[0]); ?>" alt="<?php echo t_e($luna_name); ?>, imaginea 1" data-main-photo fetchpriority="high" decoding="async"/>
              <span class="lp-zoom"><?php echo $luna_icon('zoom'); ?></span>
            </button>
          <?php else : ?>
            <div class="lp-empty-gallery">Galeria foto este în pregătire.</div>
          <?php endif; ?>
        </div>
        <?php if ($luna_count > 1) : ?>
          <div class="lp-thumbs" role="group" aria-label="Selectează imaginea">
            <?php foreach ($luna_images as $i => $luna_src) : ?>
              <button type="button" class="lp-thumb" data-gallery-index="<?php echo (int) $i; ?>"
                      aria-label="Imaginea <?php echo (int) $i + 1; ?> din <?php echo $luna_count; ?>"
                      aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>">
                <img src="<?php echo t_e($luna_src); ?>" alt="" loading="lazy" decoding="async"/>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($product_data['concept']) && $luna_count) : ?>
          <p class="lp-caption">Vizualizare de concept. Detaliile prototipului pot varia.</p>
        <?php endif; ?>
      </section>

      <div class="lp-info">
        <p class="lp-kicker"><span></span> Litofan personalizat</p>
        <h1><?php echo t_e($luna_name); ?></h1>
        <p class="lp-description"><?php echo t_e($product_data['description']); ?></p>
        <div class="lp-price-row">
          <p class="lp-price"><?php echo t_e($product_data['price']); ?></p>
          <?php if ($luna_available) : ?>
            <a class="lp-personalize-link" href="#personalizare">Personalizează Luna <?php echo t_icon('down', 16); ?></a>
          <?php endif; ?>
        </div>
        <section id="personalizare" class="lp-personalize" aria-labelledby="lp-personalize-title" tabindex="-1">
          <h2 id="lp-personalize-title">Personalizează Luna</h2>
          <?php if (function_exists('wc_print_notices')) : ?>
            <div class="lp-notices"><?php wc_print_notices(); ?></div>
          <?php endif; ?>
          <?php if ($luna_available) : ?>
          <?php if ($luna_draft) : ?>
            <p class="lp-tip lp-recovery" data-recovered-photo-draft>Am păstrat cantitatea și observațiile. Alege din nou fotografia pentru a continua.</p>
          <?php endif; ?>
          <form method="post" action="<?php echo esc_url(t_url('luna')); ?>" enctype="multipart/form-data" data-personalization>
            <input type="hidden" name="tiron_add_luna" value="1">
            <input type="hidden" name="product_id" value="<?php echo absint($product_data['id']); ?>">
            <input type="hidden" name="tiron_nonce" value="<?php echo esc_attr(tiron_personalization_nonce()); ?>">
          <label class="lp-label" for="lp-file">Fotografia ta</label>
          <p class="lp-tip lp-photo-tip" id="lp-photo-tip">Alege o imagine clară, bine luminată. Lasă spațiu între fețe și marginile fotografiei.</p>
          <div class="lp-drop" data-file-drop>
            <span class="lp-file-icon"><?php echo t_icon('photo'); ?></span>
            <span class="lp-drop-copy"><strong data-file-prompt>Alege o fotografie</strong><small>JPG, PNG sau WebP · max. <?php echo $luna_limit; ?> MB</small></span>
            <input type="file" id="lp-file" name="tiron_photo" required accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="lp-photo-tip lp-file-status"/>
          </div>
          <div class="lp-chosen" data-chosen hidden>
            <figure class="lp-photo-figure">
              <div class="lp-preview-frame" data-preview-frame>
                <img data-local-photo alt="Previzualizarea fotografiei alese"/>
                <span class="lp-guide-outline" data-guide-outline hidden aria-hidden="true"></span>
              </div>
              <figcaption><strong data-file-name></strong><small data-file-details></small></figcaption>
            </figure>
            <div class="lp-photo-actions">
              <label class="lp-guide-toggle"><input type="checkbox" checked data-guide-switch aria-describedby="lp-guide-tip"/> Arată ghidul circular</label>
              <button type="button" class="lp-clear" data-clear-photo>Elimină fotografia</button>
            </div>
            <p class="lp-tip" id="lp-guide-tip">Cercul arată aproximativ zona vizibilă a litofanului. Lasă spațiu în interiorul cercului în jurul fețelor ca să reduci riscul de decupare.</p>
          </div>
          <p class="lp-status" id="lp-file-status" role="status" aria-live="polite"></p>

          <label class="lp-label lp-note-label" for="lp-notes">Observații <span>(opțional)</span></label>
          <textarea id="lp-notes" name="tiron_notes" rows="2" maxlength="500" placeholder="Un detaliu de care să ținem cont…"><?php echo esc_textarea($luna_draft['notes'] ?? ''); ?></textarea>
          <div class="lp-action">
            <div class="lp-quantity" role="group" aria-label="Cantitate">
              <button type="button" data-step="-1" aria-label="Micșorează cantitatea" <?php disabled($luna_qty === $luna_min); ?>><?php echo $luna_icon('minus'); ?></button>
              <input type="number" id="lp-quantity" name="quantity" required value="<?php echo $luna_qty; ?>" min="<?php echo $luna_min; ?>" max="<?php echo $luna_max; ?>" step="1" inputmode="numeric" aria-label="Cantitate" aria-describedby="lp-quantity-tip"/>
              <button type="button" data-step="1" aria-label="Mărește cantitatea" <?php disabled($luna_qty === $luna_max); ?>><?php echo $luna_icon('plus'); ?></button>
            </div>
            <button type="submit" class="button-gold lp-add" data-add-product>
              <?php echo t_icon('bag'); ?><span>Adaugă în coș</span>
            </button>
          </div>
          <p class="lp-tip" id="lp-quantity-tip">Toate bucățile din acest articol folosesc aceeași fotografie.</p>
          <p class="lp-tip">Fotografia se încarcă atunci când adaugi Luna în coș.</p>
          <p class="lp-submit-status" role="status" aria-live="polite"></p>
          </form>
          <?php else : ?>
            <p class="lp-tip">Personalizarea acestui produs este momentan indisponibilă.</p>
          <?php endif; ?>
        </section>

        <div class="lp-accordions">
          <details open id="livrare"><summary>Ambalare și livrare <?php echo t_icon('down'); ?></summary>
            <dl class="lp-fulfilment">
              <div><dt>Ambalare</dt><dd><?php if (!empty($product_data['packaging'])) : ?>
                <?php echo t_e($product_data['packaging']); ?>
              <?php elseif ($luna_available) : ?>
                Oferi Luna cadou? Poți menționa o preferință de ambalare în <a href="#lp-notes">observațiile comenzii</a>.
              <?php else : ?>
                Detaliile ambalajului sunt în curs de confirmare.
              <?php endif; ?></dd></div>
              <?php if (!empty($product_data['production'])) : ?>
                <div><dt>Producție</dt><dd><?php echo t_e($product_data['production']); ?></dd></div>
              <?php endif; ?>
              <div><dt>Livrare</dt><dd>Introdu adresa la finalizarea comenzii pentru a vedea opțiunile de livrare disponibile și costul lor.</dd></div>
            </dl>
          </details>
          <details><summary>Detalii de design <?php echo t_icon('down'); ?></summary>
            <dl class="lp-specs">
              <?php foreach (array('Diametru litofan' => 'diameter', 'Finisaj' => 'finish', 'Lumină' => 'light', 'Alimentare' => 'power') as $label => $key) : ?>
                <?php if (!empty($product_data[$key])) : ?>
                  <div><dt><?php echo t_e($label); ?></dt><dd><?php echo t_e($product_data[$key]); ?></dd></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </dl>
          </details>
          <details id="retur"><summary>Returul produselor personalizate <?php echo t_icon('down'); ?></summary>
            <p>Pentru produsele realizate după specificațiile tale sau personalizate în mod clar se aplică excepția de la dreptul de retragere prevăzută la art. 16 lit. c din OUG 34/2014. Drepturile legale privind conformitatea produsului se păstrează.</p>
          </details>
        </div>
      </div>
    </div>
  </div>
  <?php if ($luna_count) : ?>
    <dialog class="lp-dialog" data-lightbox aria-label="Galerie foto mărită">
      <button type="button" data-close-gallery aria-label="Închide galeria"><?php echo t_icon('close'); ?></button>
      <img data-large-photo alt="" decoding="async"/>
      <?php if ($luna_count > 1) : ?>
        <button type="button" data-gallery-step="-1" aria-label="Imaginea anterioară"><?php echo t_icon('prev'); ?></button>
        <button type="button" data-gallery-step="1" aria-label="Imaginea următoare"><?php echo t_icon('next'); ?></button>
      <?php endif; ?>
      <output data-lightbox-count aria-live="polite"></output>
    </dialog>
  <?php endif; ?>
</main>

<?php
/** WooCommerce product, gallery, and private photo personalization form. */
$luna_name = $product_data['name'];
$luna_images = array_values(array_filter($product_data['images']));
$luna_count = count($luna_images);
$luna_mobile_images = $product_data['concept_mobile_images'];
$luna_has_portraits = count(array_filter($luna_mobile_images)) === 3;
$luna_limit = (int) $product_data['limit_mb'];
$luna_min = (int) $product_data['minimum'];
$luna_max = max($luna_min, (int) $product_data['maximum']);
$luna_available = $product_data['available'];
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
      data-in-stock="<?php echo $luna_available ? 'true' : 'false'; ?>">
  <div class="lp-wrap">
    <nav class="lp-breadcrumb" aria-label="Parcurs de navigare">
      <a href="<?php echo t_e(t_url('home')); ?>">Acasă</a>
      <span aria-hidden="true">/</span><span aria-current="page"><?php echo t_e($luna_name); ?></span>
    </nav>
    <div class="lp-layout">
      <section class="lp-gallery" aria-label="Galerie foto produs">
        <div class="lp-media"<?= $luna_has_portraits ? ' data-concepts="portrait"' : ''; ?>>
          <?php if ($luna_count) : ?>
            <button type="button" class="lp-photo-open" aria-label="Mărește imaginea produsului" aria-haspopup="dialog" data-photo-open>
              <picture>
                <?php if ($luna_has_portraits) : ?><source media="(max-width: 800px)" srcset="<?= esc_url($luna_mobile_images[0]); ?>"><?php endif; ?>
                <img src="<?= esc_url($luna_images[0]); ?>" alt="<?= t_e($luna_name); ?>, imaginea 1" data-main-photo fetchpriority="high" decoding="async">
              </picture>
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
                <picture>
                  <?php if ($luna_has_portraits) : ?><source media="(max-width: 800px)" srcset="<?= esc_url($luna_mobile_images[$i]); ?>"><?php endif; ?>
                  <img src="<?= esc_url($luna_src); ?>" alt="" loading="lazy" decoding="async">
                </picture>
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
        <p class="lp-price"><?php echo t_e($product_data['price']); ?></p>
        <section id="personalizare" class="lp-personalize" aria-labelledby="lp-personalize-title">
          <h2 id="lp-personalize-title">Personalizează Luna</h2>
          <?php if (function_exists('wc_print_notices')) : ?>
            <div class="lp-notices"><?php wc_print_notices(); ?></div>
          <?php endif; ?>
          <?php if ($luna_available) : ?>
          <form method="post" action="<?php echo esc_url(t_url('luna')); ?>" enctype="multipart/form-data" data-personalization>
            <input type="hidden" name="tiron_add_luna" value="1">
            <input type="hidden" name="product_id" value="<?php echo absint($product_data['id']); ?>">
            <input type="hidden" name="tiron_nonce" value="<?php echo esc_attr(tiron_personalization_nonce()); ?>">
          <label class="lp-label" for="lp-file">Fotografia ta</label>
          <div class="lp-drop" data-file-drop>
            <span class="lp-file-icon"><?php echo t_icon('photo'); ?></span>
            <span class="lp-drop-copy"><strong>Alege o fotografie</strong><small>JPG, PNG sau WebP · max. <?php echo $luna_limit; ?> MB</small></span>
            <input type="file" id="lp-file" name="tiron_photo" required accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="lp-photo-tip lp-file-status"/>
          </div>
          <div class="lp-chosen" data-chosen hidden>
            <img data-local-photo alt="Previzualizarea fotografiei alese"/>
            <div><strong data-file-name></strong><small>Fotografie selectată</small></div>
            <button type="button" class="lp-clear" data-clear-photo aria-label="Elimină fotografia"><?php echo t_icon('close'); ?></button>
          </div>
          <p class="lp-tip" id="lp-photo-tip">Alege o imagine clară, bine luminată, cu subiectul aproape de centru.</p>
          <p class="lp-status" id="lp-file-status" role="status" aria-live="polite"></p>

          <label class="lp-label lp-note-label" for="lp-notes">Observații <span>(opțional)</span></label>
          <textarea id="lp-notes" name="tiron_notes" rows="2" maxlength="500" placeholder="Un detaliu de care să ținem cont…"></textarea>
          <div class="lp-action">
            <div class="lp-quantity" role="group" aria-label="Cantitate">
              <button type="button" data-step="-1" aria-label="Micșorează cantitatea" disabled><?php echo $luna_icon('minus'); ?></button>
              <input type="number" id="lp-quantity" name="quantity" required value="<?php echo $luna_min; ?>" min="<?php echo $luna_min; ?>" max="<?php echo $luna_max; ?>" step="1" inputmode="numeric" aria-label="Cantitate"/>
              <button type="button" data-step="1" aria-label="Mărește cantitatea" <?php disabled($luna_min === $luna_max); ?>><?php echo $luna_icon('plus'); ?></button>
            </div>
            <button type="submit" class="lp-add" data-add-product>
              <?php echo t_icon('bag'); ?><span>Adaugă în coș</span>
            </button>
          </div>
          <p class="lp-tip">Fotografia se încarcă la adăugarea în coș, pentru personalizarea litofanului tău.</p>
          <p class="lp-submit-status" role="status" aria-live="polite"></p>
          </form>
          <?php else : ?>
            <p class="lp-tip">Personalizarea acestui produs este momentan indisponibilă.</p>
          <?php endif; ?>
        </section>

        <div class="lp-accordions">
          <details open><summary>Detalii de design <?php echo t_icon('down'); ?></summary>
            <dl class="lp-specs">
              <?php foreach (array('Diametru litofan' => 'diameter', 'Finisaj' => 'finish', 'Lumină' => 'light', 'Alimentare' => 'power') as $label => $key) : ?>
                <?php if (!empty($product_data[$key])) : ?>
                  <div><dt><?php echo t_e($label); ?></dt><dd><?php echo t_e($product_data[$key]); ?></dd></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </dl>
          </details>
          <details><summary>Producție și livrare <?php echo t_icon('down'); ?></summary>
            <p><?php echo t_e($product_data['production'] ?: 'Termenul de producție este în curs de stabilire.'); ?></p>
            <p>Metodele disponibile și costul livrării sunt afișate pentru adresa introdusă la finalizarea comenzii.</p>
          </details>
          <details><summary>Returul produselor personalizate <?php echo t_icon('down'); ?></summary>
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

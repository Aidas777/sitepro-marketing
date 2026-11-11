<?php

use app\components\KeywordService;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array $settings */
/** @var array $records */
/** @var array $ads */
/** @var array $imports */
/** @var array $excluded */
/** @var array $stats */

$this->title = 'Keyword operations | Site.pro';
$languageNames = ['en' => 'English', 'lt' => 'Lithuanian', 'de' => 'German', 'es' => 'Spanish', 'fr' => 'French'];
$sourceNames = KeywordService::SOURCES;
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">MARKETING AUTOMATION</div>
        <h1>Keyword operations</h1>
        <p>Turn search data into clean, localized Google Ads campaigns.</p>
    </div>
    <div class="heading-actions">
        <span class="updated-label"><span class="status-dot"></span> Live workspace</span>
        <?= Html::a('Export Google Ads CSV', ['marketing/export'], ['class' => 'btn btn-primary']) ?>
    </div>
</section>

<section class="metric-grid" aria-label="Workspace summary">
    <article class="metric-card">
        <div class="metric-label">IMPORTED KEYWORDS</div>
        <div class="metric-value"><?= number_format($stats['keywords']) ?></div>
        <div class="metric-note">Across all connected sources</div>
    </article>
    <article class="metric-card metric-card-accent">
        <div class="metric-label">READY FOR ADS</div>
        <div class="metric-value"><?= number_format($stats['eligible']) ?></div>
        <div class="metric-note">Unique, eligible keyword groups</div>
    </article>
    <article class="metric-card">
        <div class="metric-label">FILTERED OUT</div>
        <div class="metric-value"><?= number_format($stats['excluded']) ?></div>
        <div class="metric-note">Brand, junk, used or low-volume terms</div>
    </article>
    <article class="metric-card">
        <div class="metric-label">LANGUAGES</div>
        <div class="metric-value"><?= number_format($stats['languages']) ?></div>
        <div class="metric-note">Localized ad groups and ad copy</div>
    </article>
</section>

<div class="workspace-grid">
    <section class="panel import-panel">
        <div class="panel-heading">
            <div>
                <div class="eyebrow">STEP 01</div>
                <h2>Import data</h2>
            </div>
            <span class="panel-icon">01</span>
        </div>
        <p class="panel-description">Upload keyword data from Ads, Search Console or Ahrefs. CSV and JSON files up to 5 MB are supported.</p>
        <?= Html::beginForm(['marketing/upload'], 'post', ['enctype' => 'multipart/form-data', 'class' => 'upload-form']) ?>
            <label class="field-label" for="source">Data source</label>
            <?= Html::dropDownList('source', null, $sourceNames, ['id' => 'source', 'class' => 'form-select', 'required' => true]) ?>
            <label class="field-label" for="default-language">Default language if file has none</label>
            <?= Html::dropDownList('default_language', 'en', ['en' => 'English', 'lt' => 'Lithuanian', 'de' => 'German', 'es' => 'Spanish', 'fr' => 'French'], ['id' => 'default-language', 'class' => 'form-select', 'required' => true]) ?>
            <label class="field-label" for="keyword-file">Keyword file</label>
            <input id="keyword-file" class="form-control file-input" type="file" name="keywordFile" accept=".csv,.json" required>
            <div class="upload-actions">
                <?= Html::submitButton('Upload and import', ['class' => 'btn btn-dark']) ?>
                <span>CSV or JSON</span>
            </div>
        <?= Html::endForm() ?>
        <div class="sample-links">
            <span>Sample files</span>
            <?= Html::a('Google Ads', ['marketing/sample', 'file' => 'google ads keywords.csv']) ?>
            <?= Html::a('Search Console', ['marketing/sample', 'file' => 'search console queries.csv']) ?>
            <?= Html::a('Ahrefs organic', ['marketing/sample', 'file' => 'ahrefs organic keywords.csv']) ?>
            <?= Html::a('Ahrefs paid', ['marketing/sample', 'file' => 'ahrefs paid keywords.csv']) ?>
            <?= Html::a('JSON example', ['marketing/sample', 'file' => 'sample-keywords.json']) ?>
        </div>
    </section>

    <section class="panel imports-panel">
        <div class="panel-heading">
            <div>
                <div class="eyebrow">DATA SOURCES</div>
                <h2>Recent imports</h2>
            </div>
            <span class="source-count"><?= count($imports) ?> batches</span>
        </div>
        <?php if ($imports): ?>
            <div class="import-list">
                <?php foreach (array_slice($imports, 0, 5) as $import): ?>
                    <div class="import-item">
                        <div class="source-badge"><?= Html::encode(strtoupper(substr($import['source'], 0, 2))) ?></div>
                        <div class="import-info">
                            <strong><?= Html::encode($import['filename']) ?></strong>
                            <span><?= Html::encode($sourceNames[$import['source']] ?? $import['source']) ?> · <?= date('M j, H:i', (int) $import['created_at']) ?></span>
                        </div>
                        <div class="import-count"><?= (int) $import['rows_imported'] ?><small> new</small></div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <div class="empty-state">No imports yet. Upload a sample file to begin.</div>
        <?php endif ?>
    </section>
</div>

<section class="panel filter-panel">
    <?= Html::beginForm(['marketing/process'], 'post') ?>
        <div class="panel-heading filter-heading">
            <div>
                <div class="eyebrow">STEP 02</div>
                <h2>Clean and qualify</h2>
            </div>
            <span class="filter-hint">Changes update the preview immediately</span>
        </div>
        <div class="filter-grid">
            <div class="filter-control">
                <label class="field-label" for="min-volume">Minimum monthly searches</label>
                <?= Html::input('number', 'min_volume', $settings['min_volume'], ['id' => 'min-volume', 'class' => 'form-control', 'min' => 0, 'max' => 100000000, 'step' => 1, 'required' => true]) ?>
                <small>Keywords below this volume will be excluded.</small>
            </div>
            <div class="filter-control">
                <label class="field-label" for="brands">Brand names to exclude</label>
                <?= Html::textarea('brands', $settings['brands'], ['id' => 'brands', 'class' => 'form-control', 'rows' => 3]) ?>
                <small>One term per line. Matches whole words or phrases.</small>
            </div>
            <div class="filter-control">
                <label class="field-label" for="forbidden">Junk and forbidden terms</label>
                <?= Html::textarea('forbidden', $settings['forbidden'], ['id' => 'forbidden', 'class' => 'form-control', 'rows' => 3]) ?>
                <small>Also removes terms such as logins, jobs and cracks.</small>
            </div>
            <div class="filter-control">
                <label class="field-label" for="used">Already-used keywords</label>
                <?= Html::textarea('used', $settings['used'], ['id' => 'used', 'class' => 'form-control', 'rows' => 3]) ?>
                <small>One term per line. Existing terms are not exported again.</small>
            </div>
        </div>
        <div class="filter-footer">
            <span><?= count($excluded) ?> exclusion reasons · <?= $stats['duplicates'] ?> duplicates merged</span>
            <?= Html::submitButton('Save filters and refresh', ['class' => 'btn btn-primary']) ?>
        </div>
    <?= Html::endForm() ?>
</section>

<section class="panel preview-panel">
    <div class="panel-heading preview-heading">
        <div>
            <div class="eyebrow">STEP 03</div>
            <h2>Google Ads preview</h2>
            <p class="panel-description">Eligible keywords are merged across sources and grouped by language and intent.</p>
        </div>
        <?= Html::a('Download import file', ['marketing/export'], ['class' => 'btn btn-outline-primary']) ?>
    </div>
    <?php if ($ads): ?>
        <div class="table-responsive">
            <table class="table data-table preview-table">
                <thead>
                    <tr>
                        <th>Keyword</th>
                        <th>Ad group</th>
                        <th>Language</th>
                        <th>Searches</th>
                        <th>Ad preview</th>
                        <th>Final URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td><strong><?= Html::encode($ad['keyword']) ?></strong><div class="table-sub"><?= Html::encode(implode(', ', array_values($ad['sources']))) ?></div></td>
                            <td><?= Html::encode($ad['ad_group']) ?></td>
                            <td><span class="language-pill"><?= Html::encode($languageNames[$ad['language']] ?? $ad['language']) ?></span></td>
                            <td><?= number_format($ad['search_volume']) ?></td>
                            <td class="ad-copy"><strong><?= Html::encode($ad['headlines'][0]) ?></strong><span><?= Html::encode($ad['headlines'][1]) ?> · <?= Html::encode($ad['descriptions'][0]) ?></span></td>
                            <td><?= Html::a(Html::encode(parse_url($ad['target_url'], PHP_URL_HOST) ?: $ad['target_url']), $ad['target_url'], ['target' => '_blank', 'rel' => 'noopener']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">No keywords meet the current filters. Lower the search-volume threshold or review your exclusion lists.</div>
    <?php endif ?>
</section>

<section class="panel admin-panel">
    <div class="panel-heading">
        <div>
            <div class="eyebrow">ADMIN AREA</div>
            <h2>All imported keywords</h2>
        </div>
        <span class="source-count"><?= count($records) ?> records</span>
    </div>
    <div class="table-toolbar">
        <label for="keyword-search">Search records</label>
        <input id="keyword-search" class="form-control" type="search" placeholder="Filter by keyword or source">
    </div>
    <div class="table-responsive">
        <table class="table data-table admin-table" id="admin-keywords">
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Source</th>
                    <th>Language</th>
                    <th>Volume</th>
                    <th>CPC</th>
                    <th>Target URL</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><strong><?= Html::encode($record['keyword']) ?></strong></td>
                        <td><?= Html::encode($sourceNames[$record['source']] ?? $record['source']) ?></td>
                        <td><?= Html::encode(strtoupper($record['language'])) ?></td>
                        <td><?= number_format((int) $record['search_volume']) ?></td>
                        <td>$<?= number_format((float) $record['cpc'], 2) ?></td>
                        <td class="url-cell"><?= $record['target_url'] ? Html::a(Html::encode(parse_url($record['target_url'], PHP_URL_HOST) ?: $record['target_url']), $record['target_url'], ['target' => '_blank', 'rel' => 'noopener']) : '<span class="muted">—</span>' ?></td>
                        <td>
                            <?php if ($record['reason'] === ''): ?>
                                <span class="status-pill status-ready">Ready</span>
                            <?php else: ?>
                                <span class="status-pill status-excluded"><?= Html::encode($record['reason']) ?></span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?= Html::beginForm(['marketing/delete', 'id' => $record['id']], 'post', ['class' => 'inline-form', 'data' => ['confirm' => 'Remove this keyword from the workspace?']]) ?>
                                <?= Html::submitButton('Remove', ['class' => 'remove-button']) ?>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>

<?php
$this->registerJs(<<<'JS'
const search = document.getElementById('keyword-search');
const rows = document.querySelectorAll('#admin-keywords tbody tr');
search.addEventListener('input', () => {
    const query = search.value.toLowerCase();
    rows.forEach((row) => {
        row.hidden = !row.textContent.toLowerCase().includes(query);
    });
});
JS
);
?>

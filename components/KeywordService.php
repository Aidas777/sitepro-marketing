<?php

namespace app\components;

use Yii;
use yii\db\Connection;
use yii\helpers\Json;

class KeywordService
{
    const SOURCES = [
        'google_ads' => 'Google Ads',
        'search_console' => 'Search Console',
        'ahrefs_organic' => 'Ahrefs organic',
        'ahrefs_paid' => 'Ahrefs paid (competitors)',
    ];

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function initialize()
    {
        if (!$this->db->getTableSchema('keywords', true)) {
            $this->db->createCommand()->createTable('{{%keywords}}', [
                'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'source' => 'VARCHAR(32) NOT NULL',
                'keyword' => 'VARCHAR(255) NOT NULL',
                'keyword_norm' => 'VARCHAR(255) NOT NULL',
                'search_volume' => 'INTEGER NOT NULL DEFAULT 0',
                'cpc' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                'language' => "VARCHAR(8) NOT NULL DEFAULT 'en'",
                'target_url' => "VARCHAR(2048) NOT NULL DEFAULT ''",
                'created_at' => 'INTEGER NOT NULL',
            ])->execute();
            $this->db->createCommand()->createIndex(
                'keywords_source_norm_lang',
                '{{%keywords}}',
                ['source', 'keyword_norm', 'language'],
                true
            )->execute();
        }
        if (!$this->db->getTableSchema('import_batches', true)) {
            $this->db->createCommand()->createTable('{{%import_batches}}', [
                'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'source' => 'VARCHAR(32) NOT NULL',
                'filename' => 'VARCHAR(255) NOT NULL',
                'rows_imported' => 'INTEGER NOT NULL DEFAULT 0',
                'rows_merged' => 'INTEGER NOT NULL DEFAULT 0',
                'created_at' => 'INTEGER NOT NULL',
            ])->execute();
        }
        if (!$this->db->getTableSchema('settings', true)) {
            $this->db->createCommand()->createTable('{{%settings}}', [
                'name' => 'VARCHAR(64) PRIMARY KEY',
                'value' => 'TEXT NOT NULL',
            ])->execute();
        }

        if ((int) $this->db->createCommand('SELECT COUNT(*) FROM {{%keywords}}')->queryScalar() === 0) {
            $this->seedSamples();
        }
    }

    public function settings()
    {
        $defaults = [
            'min_volume' => '20',
            'brands' => "site.pro\nsitepro\nwix\nsquarespace\nwebflow\nwordpress\ngodaddy\nweebly",
            'forbidden' => "free download\ncrack\nlogin\nsign in\ncustomer support\njobs\ncareers",
            'used' => "website builder\ncreate a website",
        ];
        $stored = $this->db->createCommand('SELECT name, value FROM {{%settings}}')->queryAll();
        foreach ($stored as $setting) {
            $defaults[$setting['name']] = $setting['value'];
        }

        return $defaults;
    }

    public function saveSettings(array $values)
    {
        $minimum = filter_var($values['min_volume'] ?? null, FILTER_VALIDATE_INT);
        if ($minimum === false || $minimum < 0 || $minimum > 100000000) {
            throw new \InvalidArgumentException('Minimum search volume must be a whole number from 0 to 100,000,000.');
        }

        $this->setSetting('min_volume', (string) $minimum);
        foreach (['brands', 'forbidden', 'used'] as $name) {
            $lines = preg_split('/[\r\n,]+/', (string) ($values[$name] ?? ''));
            $terms = [];
            foreach ($lines as $line) {
                $term = trim($line);
                if ($term !== '') {
                    $terms[$this->normalize($term)] = $term;
                }
            }
            $this->setSetting($name, implode("\n", array_values($terms)));
        }
    }

    public function importFile($path, $filename, $source, $defaultLanguage = 'en')
    {
        if (!isset(self::SOURCES[$source])) {
            throw new \InvalidArgumentException('Choose one of the supported data sources.');
        }
        if (!in_array($defaultLanguage, ['en', 'lt', 'de', 'es', 'fr'], true)) {
            throw new \InvalidArgumentException('Choose a supported default language.');
        }
        if (!is_file($path) || !is_readable($path) || filesize($path) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('The uploaded file is missing, unreadable, or larger than 5 MB.');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            $rows = $this->parseJson($path);
        } elseif ($extension === 'csv') {
            $rows = $this->parseCsv($path);
        } else {
            throw new \InvalidArgumentException('Only CSV and JSON files are supported.');
        }
        if (!$rows) {
            throw new \InvalidArgumentException('No keyword rows were found in this file.');
        }
        $hasKeyword = false;
        foreach ($rows as $row) {
            if (is_array($row) && trim((string) $this->value($row, ['keyword', 'search term', 'search query', 'query', 'term'])) !== '') {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) {
            throw new \InvalidArgumentException('The file contains no non-empty keyword values.');
        }

        $imported = 0;
        $merged = 0;
        $transaction = $this->db->beginTransaction();
        try {
            foreach ($rows as $offset => $row) {
                $keyword = trim((string) $this->value($row, ['keyword', 'search term', 'search query', 'query', 'term']));
                if ($keyword === '') {
                    continue;
                }
                if (mb_strlen($keyword) > 255 || preg_match('/^[=+\-@]/u', $keyword)) {
                    throw new \InvalidArgumentException('Invalid keyword on data row ' . ($offset + 2) . '.');
                }

                $volume = $this->number($this->value($row, ['search volume', 'volume', 'avg monthly searches']));
                $cpc = $this->number($this->value($row, ['cpc', 'cost per click']));
                $language = $this->normalizeLanguage(
                    (string) $this->value($row, ['language', 'lang', 'language code', 'country']),
                    $keyword,
                    $defaultLanguage
                );
                $url = trim((string) $this->value($row, ['target url', 'url', 'final url', 'landing page']));
                $urlParts = $url === '' ? [] : parse_url($url);
                if ($url !== '' && (
                    !filter_var($url, FILTER_VALIDATE_URL)
                    || !isset($urlParts['scheme'], $urlParts['host'])
                    || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)
                )) {
                    throw new \InvalidArgumentException('Invalid target URL on data row ' . ($offset + 2) . '.');
                }

                $normalized = $this->normalize($keyword);
                $existing = $this->db->createCommand(
                    'SELECT id FROM {{%keywords}} WHERE source = :source AND keyword_norm = :keyword AND language = :language',
                    [':source' => $source, ':keyword' => $normalized, ':language' => $language]
                )->queryScalar();
                $data = [
                    'keyword' => $keyword,
                    'search_volume' => $volume,
                    'cpc' => $cpc,
                    'target_url' => $url,
                ];
                if ($existing) {
                    $this->db->createCommand()->update('{{%keywords}}', $data, ['id' => $existing])->execute();
                    $merged++;
                } else {
                    $this->db->createCommand()->insert('{{%keywords}}', $data + [
                        'source' => $source,
                        'keyword_norm' => $normalized,
                        'language' => $language,
                        'created_at' => time(),
                    ])->execute();
                    $imported++;
                }
            }
            $this->db->createCommand()->insert('{{%import_batches}}', [
                'source' => $source,
                'filename' => basename($filename),
                'rows_imported' => $imported,
                'rows_merged' => $merged,
                'created_at' => time(),
            ])->execute();
            $transaction->commit();
        } catch (\Throwable $error) {
            $transaction->rollBack();
            throw $error;
        }

        return ['imported' => $imported, 'merged' => $merged];
    }

    public function dashboard()
    {
        $settings = $this->settings();
        $records = $this->db->createCommand('SELECT * FROM {{%keywords}} ORDER BY search_volume DESC, keyword ASC')->queryAll();
        $excluded = [];
        $groups = [];
        $eligibleRows = 0;

        foreach ($records as &$record) {
            $record['reason'] = $this->exclusionReason($record, $settings);
            if ($record['reason'] !== '') {
                $excluded[$record['reason']] = ($excluded[$record['reason']] ?? 0) + 1;
                continue;
            }
            $eligibleRows++;

            $key = $record['language'] . '|' . $record['keyword_norm'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'keyword' => $record['keyword'],
                    'keyword_norm' => $record['keyword_norm'],
                    'language' => $record['language'],
                    'search_volume' => (int) $record['search_volume'],
                    'cpc' => (float) $record['cpc'],
                    'target_url' => $record['target_url'],
                    'sources' => [],
                ];
            } else {
                $groups[$key]['search_volume'] = max($groups[$key]['search_volume'], (int) $record['search_volume']);
                $groups[$key]['cpc'] = max($groups[$key]['cpc'], (float) $record['cpc']);
                if ($groups[$key]['target_url'] === '' && $record['target_url'] !== '') {
                    $groups[$key]['target_url'] = $record['target_url'];
                }
            }
            $groups[$key]['sources'][$record['source']] = self::SOURCES[$record['source']];
        }
        unset($record);

        $ads = [];
        foreach ($groups as $group) {
            $ads[] = $this->createAd($group);
        }

        $imports = $this->db->createCommand('SELECT * FROM {{%import_batches}} ORDER BY created_at DESC, id DESC')->queryAll();

        return [
            'settings' => $settings,
            'records' => $records,
            'ads' => $ads,
            'imports' => $imports,
            'excluded' => $excluded,
            'stats' => [
                'keywords' => count($records),
                'eligible' => count($ads),
                'excluded' => count($records) - $eligibleRows,
                'languages' => count(array_unique(array_column($ads, 'language'))),
                'duplicates' => $eligibleRows - count($ads),
            ],
        ];
    }

    public function exportCsv(array $ads)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Campaign', 'Ad group', 'Keyword', 'Criterion Type', 'Language', 'Final URL', 'Headline 1', 'Headline 2', 'Headline 3', 'Description 1', 'Description 2']);
        foreach ($ads as $ad) {
            fputcsv($stream, [
                'Site.pro | ' . strtoupper($ad['language']),
                $ad['ad_group'],
                $ad['keyword'],
                'Broad',
                $ad['language_name'],
                $ad['target_url'],
                $ad['headlines'][0],
                $ad['headlines'][1],
                $ad['headlines'][2],
                $ad['descriptions'][0],
                $ad['descriptions'][1],
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function deleteKeyword($id)
    {
        $this->db->createCommand()->delete('{{%keywords}}', ['id' => (int) $id])->execute();
    }

    private function seedSamples()
    {
        foreach ([
            'google_ads' => 'google ads keywords.csv',
            'search_console' => 'search console queries.csv',
            'ahrefs_organic' => 'ahrefs organic keywords.csv',
            'ahrefs_paid' => 'ahrefs paid keywords.csv',
        ] as $source => $filename) {
            $path = Yii::getAlias('@app/data/samples/' . $filename);
            if (is_file($path)) {
                $this->importFile($path, $filename, $source);
            }
        }
    }

    private function parseJson($path)
    {
        try {
            $decoded = Json::decode(file_get_contents($path));
        } catch (\yii\base\InvalidArgumentException $error) {
            throw new \InvalidArgumentException('The JSON file is not valid.');
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }
        if (is_array($decoded)) {
            $fields = array_map([$this, 'normalizeHeader'], array_keys($decoded));
            if (array_intersect($fields, ['keyword', 'search term', 'search query', 'query', 'term'])) {
                $decoded = [$decoded];
            }
        }
        if (!is_array($decoded) || !$decoded) {
            throw new \InvalidArgumentException('JSON must contain an array of keyword objects.');
        }
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('JSON must contain an array of keyword objects.');
            }
        }

        return $decoded;
    }

    private function parseCsv($path)
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \InvalidArgumentException('The CSV file could not be opened.');
        }
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new \InvalidArgumentException('The CSV file is empty.');
        }
        $delimiter = ',';
        foreach ([',', ';', "\t"] as $candidate) {
            if (substr_count($firstLine, $candidate) > substr_count($firstLine, $delimiter)) {
                $delimiter = $candidate;
            }
        }
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if (!$headers || count($headers) < 1) {
            fclose($handle);
            throw new \InvalidArgumentException('The CSV file needs a header row.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map([$this, 'normalizeHeader'], $headers);
        if (!in_array('keyword', $headers, true)
            && !array_intersect($headers, ['search term', 'search query', 'query', 'term'])) {
            fclose($handle);
            throw new \InvalidArgumentException('The CSV needs a keyword or query column.');
        }

        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function value(array $row, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return '';
    }

    private function number($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $value = preg_replace('/[^0-9,.\-]/', '', $value);
        if (strpos($value, ',') !== false && strpos($value, '.') === false) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('A volume or CPC value is not a valid number.');
        }

        $number = (float) $value;
        if ($number < 0) {
            throw new \InvalidArgumentException('Search volume and CPC values cannot be negative.');
        }

        return $number;
    }

    private function normalizeLanguage($value, $keyword, $defaultLanguage)
    {
        $language = strtolower(trim($value));
        $mapped = [
            'english' => 'en', 'united states' => 'en', 'united kingdom' => 'en', 'us' => 'en', 'uk' => 'en', 'gb' => 'en',
            'lithuanian' => 'lt', 'lithuania' => 'lt',
            'german' => 'de', 'germany' => 'de',
            'spanish' => 'es', 'spain' => 'es',
            'french' => 'fr', 'france' => 'fr',
        ];
        if (isset($mapped[$language])) {
            return $mapped[$language];
        }
        $language = substr($language, 0, 2);
        if (in_array($language, ['en', 'lt', 'de', 'es', 'fr'], true)) {
            return $language;
        }
        if (preg_match('/[ąčęėįšųūž]/iu', $keyword)) {
            return 'lt';
        }
        if (preg_match('/[äöüß]/iu', $keyword)) {
            return 'de';
        }
        if (preg_match('/[ñ¿¡]/iu', $keyword)) {
            return 'es';
        }
        if (preg_match('/[àâçéèêëîïôùûüÿœ]/iu', $keyword)) {
            return 'fr';
        }

        return $defaultLanguage;
    }

    private function exclusionReason(array $record, array $settings)
    {
        if ($record['source'] === 'ahrefs_paid') {
            return 'Competitor research';
        }
        $keyword = $record['keyword_norm'];
        foreach (preg_split('/[\r\n,]+/', $settings['forbidden']) as $term) {
            if ($term !== '' && $this->containsTerm($keyword, $this->normalize($term))) {
                return 'Forbidden term';
            }
        }
        foreach (preg_split('/[\r\n,]+/', $settings['brands']) as $term) {
            if ($term !== '' && $this->containsTerm($keyword, $this->normalize($term))) {
                return 'Brand keyword';
            }
        }
        foreach (preg_split('/[\r\n,]+/', $settings['used']) as $term) {
            if ($term !== '' && $keyword === $this->normalize($term)) {
                return 'Already used';
            }
        }
        if ((int) $record['search_volume'] < (int) $settings['min_volume']) {
            return 'Below volume threshold';
        }

        return '';
    }

    private function createAd(array $group)
    {
        $language = $group['language'];
        $content = [
            'en' => ['English', 'Build Your Website', 'Meet Site.pro Builder', 'Easy, No-Code Tools', 'Create a professional website with an easy drag-and-drop builder.', 'Design, publish and grow online with Site.pro.'],
            'lt' => ['Lithuanian', 'Kurkite savo svetainę', 'Site.pro svetainių kūrėjas', 'Paprasta. Be kodo.', 'Kurkite profesionalią svetainę naudodami patogų redaktorių.', 'Projektuokite, skelbkite ir auginkite verslą internete.'],
            'de' => ['German', 'Eigene Website erstellen', 'Site.pro Website-Baukasten', 'Einfach ohne Code', 'Erstellen Sie eine professionelle Website mit wenigen Klicks.', 'Gestalten, veröffentlichen und wachsen Sie online.'],
            'es' => ['Spanish', 'Crea tu propia web', 'Creador web Site.pro', 'Fácil y sin código', 'Crea una web profesional con un editor sencillo.', 'Diseña, publica y haz crecer tu negocio online.'],
            'fr' => ['French', 'Créez votre site web', 'Créateur de sites Site.pro', 'Simple et sans code', 'Créez un site professionnel avec un éditeur intuitif.', 'Concevez, publiez et développez votre activité en ligne.'],
        ];
        $copy = $content[$language] ?? $content['en'];
        $topic = $this->topic($group['keyword']);
        $url = $group['target_url'] ?: 'https://site.pro/' . ($language === 'en' ? '' : $language . '/');

        return array_merge($group, [
            'language_name' => $copy[0],
            'ad_group' => strtoupper($language) . ' - ' . $topic,
            'headlines' => [$copy[1], $copy[2], $copy[3]],
            'descriptions' => [$copy[4], $copy[5]],
            'target_url' => $url,
        ]);
    }

    private function topic($keyword)
    {
        $term = $this->normalize($keyword);
        if (preg_match('/shop|store|ecommerce|e commerce|tienda|boutique|laden|parduotuv/u', $term)) {
            return 'Online Store';
        }
        if (preg_match('/template|theme|plantilla|vorlage|šablon|sablon|modèle/u', $term)) {
            return 'Templates';
        }
        if (preg_match('/landing|page|pagina|seite|puslap/u', $term)) {
            return 'Landing Pages';
        }

        return 'Website Builder';
    }

    private function containsTerm($text, $term)
    {
        return strpos(' ' . $text . ' ', ' ' . $term . ' ') !== false;
    }

    private function normalizeHeader($header)
    {
        return strtolower(trim(preg_replace('/[\s_\-]+/', ' ', (string) $header)));
    }

    private function normalize($text)
    {
        $text = mb_strtolower(trim((string) $text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function setSetting($name, $value)
    {
        $this->db->createCommand()->upsert('{{%settings}}', ['name' => $name, 'value' => $value], ['value' => $value])->execute();
    }
}

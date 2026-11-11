<?php

namespace app\controllers;

use Yii;
use app\components\KeywordService;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\UploadedFile;

class MarketingController extends Controller
{
    private $keywords;

    public function init()
    {
        parent::init();
        $this->keywords = new KeywordService(Yii::$app->db);
        $this->keywords->initialize();
    }

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'upload' => ['post'],
                    'process' => ['post'],
                    'delete' => ['post'],
                    'export' => ['get'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index', $this->keywords->dashboard());
    }

    public function actionUpload()
    {
        $file = UploadedFile::getInstanceByName('keywordFile');
        $source = Yii::$app->request->post('source');
        $defaultLanguage = Yii::$app->request->post('default_language', 'en');
        if ($file === null) {
            Yii::$app->session->setFlash('error', 'Choose a valid CSV or JSON file to import.');
            return $this->redirect(['index']);
        }
        if ($file->error !== UPLOAD_ERR_OK) {
            $message = 'The upload failed. Please try again.';
            if (in_array($file->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                $message = 'The file exceeds the 5 MB upload limit.';
            } elseif ($file->error === UPLOAD_ERR_PARTIAL) {
                $message = 'The file was only partially uploaded. Please try again.';
            } elseif ($file->error === UPLOAD_ERR_NO_TMP_DIR) {
                $message = 'The upload folder is unavailable. Check the local runtime/uploads folder.';
            }
            Yii::$app->session->setFlash('error', $message);
            return $this->redirect(['index']);
        }

        try {
            $result = $this->keywords->importFile($file->tempName, $file->name, $source, $defaultLanguage);
            Yii::$app->session->setFlash(
                'success',
                'Import complete: ' . $result['imported'] . ' new keywords, ' . $result['merged'] . ' updated duplicates.'
            );
        } catch (\InvalidArgumentException $error) {
            Yii::warning('Keyword import rejected: ' . $error->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', $error->getMessage());
        }

        return $this->redirect(['index']);
    }

    public function actionProcess()
    {
        try {
            $this->keywords->saveSettings(Yii::$app->request->post());
            Yii::$app->session->setFlash('success', 'Filters saved and the preview has been refreshed.');
        } catch (\InvalidArgumentException $error) {
            Yii::warning('Keyword filter update rejected: ' . $error->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', $error->getMessage());
        }

        return $this->redirect(['index']);
    }

    public function actionDelete($id)
    {
        $this->keywords->deleteKeyword($id);
        Yii::$app->session->setFlash('success', 'Keyword removed from the workspace.');

        return $this->redirect(['index']);
    }

    public function actionExport()
    {
        $ads = $this->keywords->dashboard()['ads'];
        if (!$ads) {
            Yii::$app->session->setFlash('error', 'There are no eligible keywords to export. Review your filters first.');
            return $this->redirect(['index']);
        }

        return Yii::$app->response->sendContentAsFile(
            $this->keywords->exportCsv($ads),
            'sitepro-google-ads-import.csv',
            ['mimeType' => 'text/csv; charset=UTF-8']
        );
    }

    public function actionSample($file)
    {
        $samples = [
            'google ads keywords.csv',
            'search console queries.csv',
            'ahrefs organic keywords.csv',
            'ahrefs paid keywords.csv',
            'sample-keywords.json',
        ];
        if (!in_array($file, $samples, true)) {
            throw new \yii\web\NotFoundHttpException('Sample file not found.');
        }

        return Yii::$app->response->sendFile(Yii::getAlias('@app/data/samples/' . $file), $file);
    }
}

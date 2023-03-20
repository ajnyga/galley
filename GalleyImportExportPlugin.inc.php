<?php

/**
 * @file plugins/importexport/galley/GalleyImportExportPlugin.inc.php
 *
 * Copyright (c) 2013-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyImportExportPlugin
 * @ingroup plugins_importexport_galley
 *
 * @brief Galley import/export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

class GalleyImportExportPlugin extends ImportExportPlugin {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}

	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'GalleyImportExportPlugin';
	}

	function getDisplayName() {
		return __('plugins.importexport.galley.displayName');
	}

	function getDescription() {
		return __('plugins.importexport.galley.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		return array(); // Not available via the web interface
	}

	/**
	 * Display the plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		parent::display($args, $request);
		switch (array_shift($args)) {
			case 'index':
			case '':
				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
		}
	}

	/**
	 * Execute import/export tasks using the command-line interface.
	 * @param $args Parameters to the plugin
	 */
	function executeCLI($scriptName, &$args) {

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);

		$filename = array_shift($args);
		$filesPath = array_shift($args);
		$contextPath = array_shift($args);
		$username = array_shift($args);

		if (!$filename || !$username || !$filesPath) {
			$this->usage($scriptName);
			exit();
		}

		if (!file_exists($filename)) {
			echo "fileDoesNotExist" . PHP_EOL;
			exit();
		}

		if (!file_exists($filesPath)) {
			echo "folderDoesNotExist" . PHP_EOL;
			exit();
		}

		// Make sure we have data in the file
		$data = file($filename);
		if (is_array($data) && count($data) > 0) {

			$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
			$authorDao = DAORegistry::getDAO('AuthorDAO'); /* @var $authorDao AuthorDAO */
			$contextDao = Application::getContextDAO();
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$genreDao = DAORegistry::getDAO('GenreDAO'); /* @var $genreDao GenreDAO */
			$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
			$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO'); /* @var $articleGalleyDao ArticleGalleyDAO */

			$user = $userDao->getByUsername($username);
			if (!$user) {
				echo "\033[31mERROR\033[0m unknownUser" . PHP_EOL;
				exit();
			}

			$context = $contextDao->getByPath($contextPath);
			if (!$context) {
				echo "\033[31mERROR\033[0m unknownContextPath" . PHP_EOL;
				exit();
			}

			$supportedLocales = $context->getSupportedSubmissionLocales();
			if (!is_array($supportedLocales) || count($supportedLocales) < 1) {
				$supportedLocales = array($context->getPrimaryLocale());
			}

			// we need a Genre for the files.  Assume a key of SUBMISSION as a default.
			$genre = $genreDao->getByKey('SUBMISSION', $context->getId());
			if (!$genre) {
				echo "\033[31mERROR\033[0m noGenre" . PHP_EOL;
				exit();
			}

			// Create an array with all the issueIds with issuePubDate as the key
			$issues = $issueDao->getIssues($context->getId());
			$issueIds = array();
			while ($issue = $issues->next()) {
				$issueId = $issue->getId();
				$datePublished = date('Y-m-d', strtotime($issue->getDatePublished()));
				$issueIds[$datePublished] = $issueId;
			}

			$row = 0;
			$errors = array();
			$notices = array();
			foreach ($data as $line) {

				$row++;

				// Format is:
				// articleTitle#issueDate#issueYear#file#fileLabel#fileLocale

				// Validate data row
				$parts = explode("#", $line);

				// Line has to include 5 parts
				if (count($parts) != 5) {
    				echo $row . " \033[31mERROR\033[0m data or delimeter # missing " . PHP_EOL;
    				continue;
				}

				$articleTitle = $parts[0];
				$issueDate = $parts[1];
				$file = $parts[2];
				$filePath = $filesPath."/".$file;
				$fileLabel = $parts[3];
				$fileLocale = $this->convertLocale(trim($parts[4]));

				// Validate the date (YYYY-MM-DD format)
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[1])) {
				    $errors[] = $row . " \033[31mERROR\033[0m dates should be formatted YYYY-MM-DD " . PHP_EOL;
				    continue;
				}

				// Validate the file
				if (!file_exists($filePath)) {
				    $errors[] = $row . " \033[31mERROR\033[0m  file not found " . $filePath . PHP_EOL;
				    continue;
				}

				// Validate locale
				if (!in_array($fileLocale, $supportedLocales)) {
					$errors[] = $row . " \033[31mERROR\033[0m locale not supported in context " . $fileLocale . PHP_EOL;
					continue;
				}

				// Search issue with and issueId that matches the $issueDate
				$issueSubmissions = iterator_to_array(Services::get('submission')->getMany([
						'contextId' => $context->getId(),
						'issueIds' => [$issueIds[$issueDate]],
				]));

				// Loop through submissions in the issue and find title matches
				$matchingSubmissions = array();
				foreach ($issueSubmissions as $submission) {
					$publication = $submission->getCurrentPublication();
					$publicationTitle = $publication->getData('title', $fileLocale);
					if (trim($articleTitle) == trim($publicationTitle)) {
						$matchingSubmissions[] = $submission;
					}
				}

				// Only allow cases where there is one match for the title insiden an issue, otherwise show an error
				if (count($matchingSubmissions) == 0) {
					$errors[] = $row ." \033[31mERROR\033[0m NO MATCHES FOR " . $articleTitle . PHP_EOL;
					continue;
				}
				if (count($matchingSubmissions) > 1) {
					$errors[] = $row ." \033[31mERROR\033[0m TOO MANY MATCHES FOR " . $articleTitle . PHP_EOL;
					continue;
				}

				$submission = $matchingSubmissions[0];
				$publication = $submission->getCurrentPublication();

				// Katso onko Galley jo olemassa. Jos on, anna virhe.
				$galleys = (array) $publication->getData('galleys');
				if ($galleys){
					$notices[] = $row . " \033[33;2mNOTICE\033[0m GALLEY ALREADY EXISTS FOR " . $articleTitle . "#publicationId#" . $publication->getId() . PHP_EOL;
					continue;
				}

				// Lisää uusi Galley
				$articleGalley = $articleGalleyDao->newDataObject();
				$articleGalley->setData('publicationId', $publication->getId());
				$articleGalley->setLabel($fileLabel);
				$articleGalley->setLocale($fileLocale);
				$articleGalley->setData('urlPath', null);
				$articleGalley->setData('urlRemote', null);
				$articleGalley->setFileId($dbFileId);
				$articleGalleyId = $articleGalleyDao->insertObject($articleGalley);

				// Lisää tiedosto
				import('lib.pkp.classes.file.TemporaryFileManager');
				import('lib.pkp.classes.file.FileManager');
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryFilename = tempnam($temporaryFileManager->getBasePath(), 'embed');
				$temporaryFileManager->copyFile($filePath, $temporaryFilename);

				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
				$submissionFile = $submissionFileDao->newDataObjectByGenreId($genre->getId());
				$submissionFile->setSubmissionId($submission->getId());
				$submissionFile->setSubmissionLocale($submission->getLocale());
				$submissionFile->setGenreId($genre->getId());
				$submissionFile->setFileStage(SUBMISSION_FILE_PROOF);
				$submissionFile->setDateUploaded(Core::getCurrentDate());
				$submissionFile->setDateModified(Core::getCurrentDate());
				$submissionFile->setOriginalFileName($file);
				$submissionFile->setViewable(true);
				$submissionFile->setAssocType(ASSOC_TYPE_REPRESENTATION);
				$submissionFile->setAssocId($articleGalleyId);

				if(function_exists('mime_content_type')){
					$fileType = mime_content_type($filePath);
				}
				elseif(function_exists('finfo_open')){
					$fileinfo = new finfo();
					$fileType = $fileinfo->file($filePath, FILEINFO_MIME_TYPE);
				}

				$submissionFile->setFileType($fileType);
				$submissionFile->setRevision(1);
				$submissionFile->setUploaderUserId($user->getId());
				$submissionFile->setFileSize(filesize($filePath));
				$insertedSubmissionFile = $submissionFileDao->insertObject($submissionFile, $temporaryFilename);

				$articleGalley->setFileId($insertedSubmissionFile->getFileId());
				$articleGalleyDao->updateObject($articleGalley);

				$fileManager = new FileManager();
				$fileManager->deleteByPath($temporaryFilename);

				echo $row . " SUCCESS GALLEY ADDED FOR " . $articleTitle . "#publicationId#" . $publication->getId() . PHP_EOL;
			}

			// Print all errors in the end
			foreach($errors as $error){
				echo $error;
			}
			foreach($notices as $notice){
				echo $notice;
			}

		}
	}

	/**
	 * Display the command-line usage information
	 */
	function usage($scriptName) {
		echo "php importExport.php GalleyImportExportPlugin pathToDataFile pathToGalleyFileFolder contextPath importingUsername" . PHP_EOL;
	}


	function convertLocale($locale) {
		$locales = array(
				'en' => 'en_US',
				'fi' => 'fi_FI',
				'sv' => 'sv_SE',
				'de' => 'de_DE',
				'ge' => 'de_DE',
				'ru' => 'ru_RU',
				'fr' => 'fr_FR',
				'no' => 'nb_NO',
				'da' => 'da_DK',
				'es' => 'es_ES',
		);
		return $locales[$locale];
	}


}



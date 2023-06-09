<?php

/**
 * @defgroup plugins_importexport_galley Data in tab delimited format import/export plugin
 */

/**
 * @file plugins/importexport/galley/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_galley
 * @brief Wrapper for tab delimited data import/export plugin.
 *
 */


require_once('GalleyImportExportPlugin.inc.php');

return new GalleyImportExportPlugin();



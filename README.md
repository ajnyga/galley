# Galley import pluginfor OJS 3.2

This plugin is for mass importing Galley files to articles that have been already published in OJS.

NOTICE! Always test the script on a separate server before using it in production!

The plugin can only be used from the command line.

`php importExport.php GalleyImportExportPlugin pathToDataFile pathToGalleyFileFolder contextPath importingUsername`

The data file should have a line for each galley to be imported. The structure of a single line with # being the delimeter is:

`articleTitle#issuePublishedDate#file#fileLabel#fileLocale`

See the example data file in the repository.

The plugin will read the datafile and will try to search a matching article title within an issue with the matching published date. The search is limited to the context given in the call usint a context path.

If a match is found, the plugin will make sure there is no more than one match and no prior galley is added to the article. If these checks are ok, a galley is created using the file mentioned in the datafile. Otherwise, the script will print out errors or notices concerning failed searches.

***
Plugin created by The Federation of Finnish Learned Societies (https://tsv.fi/en/).
***

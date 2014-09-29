<?php

use Symfony\Component\Finder\Finder;

    /**
     * CLI command class, main -> extract_strings
     *
     * @author Jean Traullé <jtraulle@gmail.com>
     * @version 1
     * @license http://www.opensource.org/licenses/mozilla1.1.php Mozilla Public License 1.1 (MPL 1.1)
     * @package thebuggenie
     * @subpackage core
     */

    /**
     * CLI command class, main -> extract_strings
     *
     * @package thebuggenie
     * @subpackage core
     */
    class CliMainExtractStrings extends TBGCliCommand
    {
        protected function _setup()
        {
            $this->_command_name = 'extract_strings';
            $this->_description = "Extract main language (en_US) translatable strings from sources";
            $this->addOptionalArgument('-v', "Verbose mode");
        }

        protected function do_execute()
        {
            if($this->getProvidedArgument(2) != '-v'){
                $this->cliEcho("\nFinding files to process ... ");
            }

            //Finding all .php potentially containing source
            //strings using Symfony2 Finder component.
            $finder = new Finder();
            $finder->files()
                    ->in(__DIR__."/../../../../")
                    ->notpath('core/lib')
                    ->notpath('core/cache')
                    ->notpath('i18n')
                    ->notpath('thebuggenie')
                    ->notpath('tests')
                    ->name('*.php');

            $this->cliEcho("✔ DONE", 'green', 'bold');

            $allstrings = array();
            $stringsByFile = array();

            $totalStrings = 0;
            $totalFiles = 0;
            $filesIgnored = 0;
            $filesWithoutStrings = 0;

            if($this->getProvidedArgument(2) != '-v'){
                $this->cliEcho("\n\nProcessing files ");
            }

            foreach ($finder as $file) {
                $filename = $file->getRelativePathname();
                $file = file_get_contents($file->getRealpath());

                preg_match_all("/__\(('|\")(.*?[^\\\\])(?:\\1)/", $file, $strings);
                $strings = str_replace("\\", "", $strings[2]);

                if ($this->getProvidedArgument(2) == '-v') {
                    $this->cliEcho("Processing file ");
                    $this->cliEcho($filename."\n", 'yellow');
                    $this->cliEcho(" ↳  ");
                }

                if(!empty($strings)){
                    $stringsWithoutDuplicates = array_values(array_unique($strings));
                    $stringsWithoutDuplicatesFromAllStringsTab = array_diff($stringsWithoutDuplicates, $allstrings);

                    $numberOfStringsInFile = count($stringsWithoutDuplicatesFromAllStringsTab);    

                    if($numberOfStringsInFile){
                        if ($this->getProvidedArgument(2) == '-v'){
                            $this->cliEcho($numberOfStringsInFile." ", 'green', 'bold');
                            $this->cliEcho(($numberOfStringsInFile > 1) ? "strings found\n" : "string found\n");                            
                        }else{
                            $this->cliEcho(".");
                        }

                        $totalStrings += $numberOfStringsInFile;
                        $totalFiles++;
                        $allstrings = array_merge($allstrings, $stringsWithoutDuplicatesFromAllStringsTab);
                        $stringsByFile[$filename] = array_values($stringsWithoutDuplicatesFromAllStringsTab);    
                    }else{
                        if ($this->getProvidedArgument(2) == '-v') 
                            $this->cliEcho("File ignored since found strings were already in previous processed files.\n", 'cyan');
                        $filesIgnored++;
                    }
                }else{
                    if ($this->getProvidedArgument(2) == '-v')
                        $this->cliEcho("There were no string in the file.\n", 'cyan');
                    $filesWithoutStrings++;
                }

                if ($this->getProvidedArgument(2) == '-v') $this->cliEcho("\n");
            }

            if($this->getProvidedArgument(2) != '-v'){
                $this->cliEcho(" ✔ DONE\n\n", 'green', 'bold');
            }

            //Displaying extraction report to user
            $this->cliEcho("Extraction of ");
            $this->cliEcho("en_US", "yellow"); 
            $this->cliEcho(" source strings ");
            $this->cliEcho("✔ COMPLETED\n", 'green', 'bold');

            $this->cliEcho(" ↳  ");
            $this->cliEcho($totalStrings, 'green', 'bold');
            $this->cliEcho(" strings found\n");

            $this->cliEcho(" ↳  through ");
            $this->cliEcho($totalFiles, 'green', 'bold');
            $this->cliEcho(" files\n");

            $this->cliEcho(" ↳  ");
            $this->cliEcho($filesIgnored, 'cyan', 'bold');
            $this->cliEcho(" files has been ignored (all found strings were already in previous files)\n");

            $this->cliEcho(" ↳  ");
            $this->cliEcho($filesWithoutStrings, 'cyan', 'bold');
            $this->cliEcho(" files did not contain any string\n");

            $this->cliEcho("\n");

            $this->cliEcho("Generating resulting ");
            $this->cliEcho("strings.inc.php", "yellow"); 
            $this->cliEcho(" file ...\n");        

            $generatedFile = "<?php".PHP_EOL.PHP_EOL;

            $generatedFile .= "// Please, do not edit this file !".PHP_EOL;
            $generatedFile .= "// If you would like to help translating TBG,".PHP_EOL;
            $generatedFile .= "// please visit https://www.transifex.com/projects/p/tbg".PHP_EOL.PHP_EOL;

            $generatedFile .= "// Number of Sections: $totalFiles".PHP_EOL;
            $generatedFile .= "// Number of Strings: $totalStrings".PHP_EOL;
            $generatedFile .= "// Keys extracted from sources on: ".date('Y M d.').PHP_EOL;
            $generatedFile .= "// Translations extracted from Transifex on: ".PHP_EOL;

            foreach ($stringsByFile as $file => $strings) {

                $generatedFile .= PHP_EOL;
                $generatedFile .= "// First occurrence is in: ".$file.PHP_EOL;
                $generatedFile .= "// ----------------------------------------------------------------------------".PHP_EOL;

                foreach ($strings as $string) {
                    $string = trim($string);

                    if (strpos($string, "'") !== FALSE)
                        $generatedFile .= '  $strings["' . str_replace('"', '\\"', $string) . '"] = "' . str_replace('"', '\\"', $string) . '";' . PHP_EOL;
                    else
                        $generatedFile .= '  $strings[\'' . str_replace("'", "\\'", $string) . '\'] = \'' . str_replace("'", "\\'", $string) . '\';' . PHP_EOL;
                }
            }

            file_put_contents(__DIR__."/../../../../i18n/en_US/strings.inc.php", $generatedFile);

            $this->cliEcho(" ↳  ");
            $this->cliEcho("i18n/en_US/strings.inc.php", 'magenta', 'bold');
            $this->cliEcho(" ✔ GENERATED\n\n", 'green', 'bold');
        }
    }
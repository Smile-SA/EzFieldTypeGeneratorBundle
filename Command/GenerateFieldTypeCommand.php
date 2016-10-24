<?php

namespace Smile\EzFieldTypeGeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Smile\EzFieldTypeGeneratorBundle\Generator\FieldTypeGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\KernelInterface;

class GenerateFieldTypeCommand extends GeneratorCommand
{
    /**
     * Configure FieldType generator command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace of the bundle to create'),
                new InputOption('dir', '', InputOption::VALUE_REQUIRED, 'The directory where to create the bundle'),
                new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The optional bundle name'),
                new InputOption('fieldtype-name', '', InputOption::VALUE_REQUIRED, 'The field type name'),
                new InputOption('yui-fieldtype-namespace', '', InputOption::VALUE_REQUIRED, 'The field type namespace')
            ))
            ->setHelp(<<<EOT
The <info>generate:fieldtype</info> command helps you generates new FieldType bundles.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction
(<comment>--namespace --fieldtype-name --yui-fieldtype-namespace</comment> are needed if you follow the
conventions):

<info>php app/console generate:fieldtype --namespace=Acme/FooBundle --fieldtype-name=Foo --yui-fieldtype-namespace=acme</info>

Note that you can use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any
problem.

If you want to disable any user interaction, use <comment>--no-interaction</comment> but don't forget to pass 
all needed options:

<info>php app/console generate:fieldtype --namespace=Acme/FooBundle --dir=src --fieldtype-name=Foo --yui-fieldtype-namespace=acme
[--bundle-name=...] --no-interaction</info>

Note that the bundle namespace must end with "Bundle".
EOT
            )
            ->setName('generate:fieldtype')
            ->setDescription('Generate Structure code for new eZ Platform FieldType');
    }

    /**
     * Execute FieldType generate command
     *
     * @param InputInterface $input console input
     * @param OutputInterface $output console output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        if ($input->isInteractive()) {
            $question = new Question($questionHelper->getQuestion('Do you confirm generation', 'yes', '?'), true);
            if (!$questionHelper->ask($input, $output, $question)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        foreach (array('namespace', 'dir', 'fieldtype-name', 'yui-fieldtype-namespace') as $option) {
            if (null === $input->getOption($option)) {
                throw new \RuntimeException(sprintf('The "%s" option must be provided.', $option));
            }
        }

        // validate the namespace, but don't require a vendor namespace
        $namespace = Validators::validateBundleNamespace($input->getOption('namespace'), false);
        if (!$bundle = $input->getOption('bundle-name')) {
            $bundle = strtr($namespace, array('\\' => ''));
        }
        $bundle = Validators::validateBundleName($bundle);
        $dir = Validators::validateTargetDir($input->getOption('dir'), $bundle, $namespace);
        $fieldTypeName = FieldTypeValidators::validateFieldTypeName($input->getOption('fieldtype-name'));
        $yuiFieldTypeNamespace = FieldTypeValidators::validateFieldTypeNamespace($input->getOption('yui-fieldtype-namespace'));

        $questionHelper->writeSection($output, 'Field Type structure generation');

        if (!$this->getContainer()->get('filesystem')->isAbsolutePath($dir)) {
            $dir = getcwd().'/'.$dir;
        }

        $generator = $this->getGenerator();
        $generator->generate($namespace, $bundle, $dir, $fieldTypeName, $yuiFieldTypeNamespace);

        $output->writeln('Generating the Field Type structure code: <info>OK</info>');

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // check that the namespace is already autoloaded
        $runner($this->checkAutoloader($output, $namespace, $bundle, $dir));

        // register the bundle in the Kernel class
        $runner(
            $this->updateKernel(
                $questionHelper,
                $input,
                $output,
                $this->getContainer()->get('kernel'),
                $namespace,
                $bundle
            )
        );

        $questionHelper->writeGeneratorSummary($output, $errors);
    }

    protected function checkAutoloader(OutputInterface $output, $namespace, $bundle, $dir)
    {
        $output->write('Checking that the bundle is autoloaded: ');
        if (!class_exists($namespace.'\\'.$bundle)) {
            return array(
                '- Edit the <comment>composer.json</comment> file and register the bundle',
                '  namespace in the "autoload" section:',
                '',
            );
        }
    }

    protected function updateKernel(
        QuestionHelper $questionHelper,
        InputInterface $input,
        OutputInterface $output,
        KernelInterface $kernel,
        $namespace, $bundle
    ) {
        $auto = true;
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion(
                $questionHelper->getQuestion(
                    'Confirm automatic update of your Kernel',
                    'yes',
                    '?'
                ),
                true
            );
            $auto = $questionHelper->ask($input, $output, $question);
        }

        $output->write('Enabling the bundle inside the Kernel: ');
        $manip = new KernelManipulator($kernel);
        try {
            $ret = $auto ? $manip->addBundle($namespace.'\\'.$bundle) : false;

            if (!$ret) {
                $reflected = new \ReflectionObject($kernel);

                return array(
                    sprintf('- Edit <comment>%s</comment>', $reflected->getFilename()),
                    '  and add the following bundle in the <comment>AppKernel::registerBundles()</comment> method:',
                    '',
                    sprintf('    <comment>new %s(),</comment>', $namespace.'\\'.$bundle),
                    '',
                );
            }
        } catch (\RuntimeException $e) {
            return array(
                sprintf(
                    'Bundle <comment>%s</comment> is already defined in <comment>AppKernel::registerBundles()</comment>.',
                    $namespace.'\\'.$bundle
                ),
                '',
            );
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the eZ Platform FieldType bundle generator');

        $namespace = $this->getNamespace($input, $output, $questionHelper);
        $fieldTypeName = $this->getFieldTypeName($input, $output, $questionHelper);
        $yuiFieldTypeNamespace = $this->getYuiFieldTypeNamespace($input, $output, $questionHelper);
        $bundle = $this->getBundle($input, $output, $questionHelper, $namespace);
        $dir = $this->getDir($input, $output, $questionHelper, $bundle, $namespace);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf(
                "You are going to generate a \"<info>%s\\%s</info>\" FieldType bundle\nin \"<info>%s</info>\"\n with fieldtype name \"<info>%s</info>\" and YUI namspace \"<info>%s</info>\".",
                $namespace,
                $bundle,
                $dir,
                $fieldTypeName,
                $yuiFieldTypeNamespace
            ),
            '',
        ));
    }

    private function getNamespace(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        // namespace
        $namespace = null;
        try {
            // validate the namespace option (if any) but don't require the vendor namespace
            $namespace = $input->getOption('namespace')
                ? Validators::validateBundleNamespace($input->getOption('namespace'), false)
                : null;
        } catch (\Exception $error) {
            $output->writeln(
                $questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error')
            );
        }

        if (null === $namespace) {
            $acceptedNamespace = false;
            while (!$acceptedNamespace) {
                $question = new Question(
                    $questionHelper->getQuestion(
                        'FieldType Bundle namespace',
                        $input->getOption('namespace')
                    ),
                    $input->getOption('namespace')
                );
                $question->setValidator(function ($answer) {
                    return Validators::validateBundleNamespace($answer, false);

                });
                $namespace = $questionHelper->ask($input, $output, $question);

                // mark as accepted, unless they want to try again below
                $acceptedNamespace = true;

                // see if there is a vendor namespace. If not, this could be accidental
                if (false === strpos($namespace, '\\')) {
                    // language is (almost) duplicated in Validators
                    $msg = array();
                    $msg[] = '';
                    $msg[] = sprintf(
                        'The namespace sometimes contain a vendor namespace (e.g. <info>VendorName/BlogBundle</info> instead of simply <info>%s</info>).',
                        $namespace,
                        $namespace
                    );
                    $msg[] = 'If you\'ve *did* type a vendor namespace, try using a forward slash <info>/</info> (<info>Acme/BlogBundle</info>)?';
                    $msg[] = '';
                    $output->writeln($msg);

                    $question = new ConfirmationQuestion($questionHelper->getQuestion(
                        sprintf(
                            'Keep <comment>%s</comment> as the fieldtype bundle namespace (choose no to try again)?',
                            $namespace
                        ),
                        'yes'
                    ), true);
                    $acceptedNamespace = $questionHelper->ask($input, $output, $question);
                }
            }
            $input->setOption('namespace', $namespace);
        }

        return $namespace;
    }

    private function getFieldTypeName(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        // fieldtype-name
        $fieldTypeName = null;
        try {
            // validate the fieldtype-name option (if any)
            $fieldTypeName = $input->getOption('fieldtype-name')
                ? FieldTypeValidators::validateFieldTypeName($input->getOption('fieldtype-name'))
                : null;
        } catch (\Exception $error) {
            $output->writeln(
                $questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error')
            );
        }

        if (null === $fieldTypeName) {
            $acceptedFieldTypeName = false;
            while (!$acceptedFieldTypeName) {
                $question = new Question(
                    $questionHelper->getQuestion(
                        'FieldType name',
                        $input->getOption('fieldtype-name')
                    ),
                    $input->getOption('fieldtype-name')
                );
                $question->setValidator(function ($answer) {
                    return FieldTypeValidators::validateFieldTypeName($answer);

                });
                $fieldTypeName = $questionHelper->ask($input, $output, $question);

                // mark as accepted, unless they want to try again below
                $acceptedFieldTypeName = true;
            }
            $input->setOption('fieldtype-name', $fieldTypeName);
        }

        return $fieldTypeName;
    }

    private function getYuiFieldTypeNamespace(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper)
    {
        // yui-fieldtype-namespace
        $yuiFieldTypeNamespace = null;
        try {
            // validate the yui-fieldtype-namespace option (if any)
            $yuiFieldTypeNamespace = $input->getOption('yui-fieldtype-namespace')
                ? FieldTypeValidators::validateFieldTypeNamespace($input->getOption('yui-fieldtype-namespace'))
                : null;
        } catch (\Exception $error) {
            $output->writeln(
                $questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error')
            );
        }

        if (null === $yuiFieldTypeNamespace) {
            $acceptedYuiFieldTypeNamespace = false;
            while (!$acceptedYuiFieldTypeNamespace) {
                $question = new Question(
                    $questionHelper->getQuestion(
                        'YUI FieldType namespace',
                        $input->getOption('yui-fieldtype-namespace')
                    ),
                    $input->getOption('yui-fieldtype-namespace')
                );
                $question->setValidator(function ($answer) {
                    return FieldTypeValidators::validateFieldTypeNamespace($answer);

                });
                $yuiFieldTypeNamespace = $questionHelper->ask($input, $output, $question);

                // mark as accepted, unless they want to try again below
                $acceptedYuiFieldTypeNamespace = true;
            }
            $input->setOption('yui-fieldtype-namespace', $yuiFieldTypeNamespace);
        }

        return $yuiFieldTypeNamespace;
    }

    private function getBundle(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
        $namespace
    ) {
        // bundle name
        $bundle = null;
        try {
            $bundle = $input->getOption('bundle-name')
                ? Validators::validateBundleName($input->getOption('bundle-name'))
                : null;
        } catch (\Exception $error) {
            $output->writeln(
                $questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error')
            );
        }

        if (null === $bundle) {
            $bundle = strtr($namespace, array('\\Bundle\\' => '', '\\' => ''));

            $output->writeln(array(
                '',
                'In your code, a bundle is often referenced by its name. It can be the',
                'concatenation of all namespace parts but it\'s really up to you to come',
                'up with a unique name (a good practice is to start with the vendor name).',
                'Based on the namespace, we suggest <comment>'.$bundle.'</comment>.',
                '',
            ));
            $question = new Question($questionHelper->getQuestion('FieldType Bundle name', $bundle), $bundle);
            $question->setValidator(
                array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName')
            );
            $bundle = $questionHelper->ask($input, $output, $question);
            $input->setOption('bundle-name', $bundle);
        }

        return $bundle;
    }

    private function getDir(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper,
        $bundle,
        $namespace
    ) {
        // target dir
        $dir = null;
        try {
            $dir = $input->getOption('dir')
                ? Validators::validateTargetDir($input->getOption('dir'), $bundle, $namespace)
                : null;
        } catch (\Exception $error) {
            $output->writeln($questionHelper->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }

        if (null === $dir) {
            $dir = dirname($this->getContainer()->getParameter('kernel.root_dir')).'/src';

            $output->writeln(array(
                '',
                'The bundle can be generated anywhere. The suggested default directory uses',
                'the standard conventions.',
                '',
            ));
            $question = new Question($questionHelper->getQuestion('Target directory', $dir), $dir);
            $question->setValidator(function ($dir) use ($bundle, $namespace) {
                return Validators::validateTargetDir($dir, $bundle, $namespace);
            });
            $dir = $questionHelper->ask($input, $output, $question);
            $input->setOption('dir', $dir);
        }
    }

    /**
     * Initialize FieldType generator
     *
     * @return FieldTypeGenerator FieldType generator
     */
    protected function createGenerator()
    {
        return new FieldTypeGenerator(
            $this->getContainer()->get('kernel')
        );
    }
}

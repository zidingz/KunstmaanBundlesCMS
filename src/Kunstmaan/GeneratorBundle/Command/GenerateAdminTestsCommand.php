<?php

namespace Kunstmaan\GeneratorBundle\Command;

use Kunstmaan\GeneratorBundle\Generator\AdminTestsGenerator;
use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Kunstmaan\GeneratorBundle\Helper\Sf4AppBundle;
use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * GenerateAdminTestsCommand
 */
class GenerateAdminTestsCommand extends GeneratorCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(
                [
                    new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace to generate the tests in'),
                ]
            )
            ->setDescription('Generates the tests used to test the admin created by the default-site generator')
            ->setHelp(<<<'EOT'
The <info>kuma:generate:admin-test</info> command generates tests to test the admin generated by the default-site generator

<info>php bin/console kuma:generate:admin-tests --namespace=Namespace/NamedBundle</info>

EOT
            )
            ->setName('kuma:generate:admin-tests');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Admin Tests Generation');

        $bundle = new Sf4AppBundle($this->getContainer()->getParameter('kernel.project_dir'));
        if (Kernel::VERSION_ID < 40000) {
            GeneratorUtils::ensureOptionsProvided($input, ['namespace']);

            $namespace = Validators::validateBundleNamespace($input->getOption('namespace'));
            $bundle = strtr($namespace, ['\\Bundle\\' => '', '\\' => '']);

            $bundle = $this
                ->getApplication()
                ->getKernel()
                ->getBundle($bundle);
        }

        $generator = $this->getGenerator($this->getApplication()->getKernel()->getBundle('KunstmaanGeneratorBundle'));
        $generator->generate($bundle, $output);

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (Kernel::VERSION_ID >= 40000) {
            return;
        }

        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Welcome to the Kunstmaan default site generator');

        $inputAssistant = GeneratorUtils::getInputAssistant($input, $output, $questionHelper, $this->getApplication()->getKernel(), $this->getContainer());

        $inputAssistant->askForNamespace([
            '',
            'This command helps you to generate tests to test the admin of the default site setup.',
            'You must specify the namespace of the bundle where you want to generate the tests.',
            'Use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any problem.',
            '',
        ]);
    }

    protected function createGenerator()
    {
        return new AdminTestsGenerator($this->getContainer(), $this->getContainer()->get('filesystem'), '/admintests');
    }
}

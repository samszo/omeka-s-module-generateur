<?php declare(strict_types=1);

namespace BulkExport\Writer;

use BulkExport\Traits\ListTermsTrait;
use BulkExport\Traits\MetadataToStringTrait;
use BulkExport\Traits\ResourceFieldsTrait;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

abstract class AbstractGenerateuroldWriter extends AbstractWriter
{
    use ListTermsTrait;
    use MetadataToStringTrait;
    use ResourceFieldsTrait;

    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 100;
    
    protected $label = "Generateur Old Writer";

    protected $configKeys = [
        'db_name'
    ];

    protected $paramsKeys = [
        'db_name',
    ];

    protected $options = [
        'db_name' => 'generateur',
    ];

    /**
     * Json resource types.
     *
     * @var array
     */
    protected $resourceTypes = [];

    /**
     * @var array
     */
    protected $stats;

    /**
     * @var bool
     */
    protected $jobIsStopped = false;

    /**
     * @var bool
     */
    protected $hasError = false;

    /**
     * @var bool
     */
    protected $prependFieldNames = false;

    /**
     * @var array
     */
    protected $keys = false;
    /**
     * @var array
     */
    protected $rs = false;
    /**
     * @var array
     */
    protected $data = [];
    protected $querySQL;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    public function process(): self
    {
        $this->translator = $this->getServiceLocator()->get('MvcTranslator');

        $this
            ->initializeParams()
            ->prepareTempFile()
            ->initializeOutput();

        if ($this->hasError) {
            return $this;
        }

        $this->stats = [];
        $this->appendResources();

        $this
            ->finalizeOutput()
            ->saveFile();
        return $this;
    }

    protected function initializeParams(): self
    {
        // Merge params for simplicity.
        $this->options = $this->getParams() + $this->options;

        if (!in_array($this->options['format_resource'], ['identifier', 'identifier_id'])) {
            $this->options['format_resource_property'] = null;
        }

        return $this;
    }

    protected function initializeOutput(): self
    {
        return $this;
    }

    /**
     * @param array $fields If fields contains arrays, this method should manage
     * them.
     */
    abstract protected function writeFields(array $fields): self;

    protected function finalizeOutput(): self
    {
        return $this;
    }

    protected function appendResources(): self
    {
        $vhm = $this->getServiceLocator()->get('ViewHelperManager');
        $this->querySQL = $vhm->get('generateurSql');
        /*pour toute la base
        $this->data = $this->querySQL->__invoke([
            'action'=>'getOldConceptDoublons'
        ]);
        */
        //pour toute les concepts oubliés
        $this->data = $this->querySQL->__invoke([
            'action'=>'getOldConceptOubli'
        ]);

        $this->rs = [];
        $this->stats['totals'] = 0;
        $this->stats['totalToProcess'] = count($this->data);
        $this->stats['processed'] = 0;
        $this->stats['succeed'] = 0;
        $this->stats['skipped'] = 0;

        if (!$this->stats['totalToProcess']) {
            $this->logger->warn('No resource to export.'); // @translate
            return $this;
        }


        foreach ($this->data as $c) {
            if ($this->job->shouldStop()) {
                $this->jobIsStopped = true;
                $this->logger->warn(
                    'The job "Export" was stopped: {processed}/{total} resources processed.', // @translate
                    ['processed' => $this->stats['processed'], 'total' => $this->stats['totalToProcess']]
                );
                return $this;
            }
    
            //récupère les usages des resources
            $rs = $this->querySQL->__invoke([
                'action'=>'getStatsOldConceptUses',
                'cpts'=>[$c]
            ]);
            $this->rs[] = $rs[0];
            $this->logger->info(
                '{processed}/{total} => {id}.', // @translate
                ['processed' => $this->stats['processed'], 'total' => $this->stats['totalToProcess'], 'id' => $c['cpt']]
            );
            ++$this->stats['succeed'];
            ++$this->stats['processed'];
        }

        $this->logger->info(
            '{processed}/{total} processed, {succeed} succeed, {skipped} skipped.', // @translate
            ['processed' => $this->stats['processed'], 'total' => $this->stats['totals'], 'succeed' => $this->stats['succeed'], 'skipped' => $this->stats['skipped']]
        );
        $this->writeFields($this->rs);

        return $this;
    }


}

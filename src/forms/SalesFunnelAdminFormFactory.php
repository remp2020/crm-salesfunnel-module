<?php

namespace Crm\SalesFunnelModule\Forms;

use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SalesFunnelModule\SalesFunnelsCache;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SalesFunnelAdminFormFactory
{
    private $salesFunnelsRepository;

    private $segmentsRepository;

    private $translator;

    private $salesFunnelsCache;

    public $onUpdate;

    public $onSave;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        SegmentsRepository $segmentsRepository,
        SalesFunnelsCache $salesFunnelsCache,
        Translator $translator
    ) {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->segmentsRepository = $segmentsRepository;
        $this->salesFunnelsCache = $salesFunnelsCache;
        $this->translator = $translator;
    }

    public function create($id)
    {
        $defaults = [];
        if (isset($id)) {
            $funnel = $this->salesFunnelsRepository->find($id);
            $defaults = $funnel->toArray();
        }

        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addText('name', 'sales_funnel.data.sales_funnels.fields.name')
            ->setAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.name')
            ->setRequired();

        $isActive = $form->addCheckbox('is_active', 'sales_funnel.data.sales_funnels.fields.is_active');

        $activeFunnels = [];
        foreach ($this->salesFunnelsRepository->active()->fetchAll() as $funnel) {
            $activeFunnels[strval($funnel->id)] = "{$funnel->name} <small>({$funnel->url_key})</small>";
        }

        $redirectFunnelId = $form->addSelect('redirect_funnel_id', 'sales_funnel.data.sales_funnels.fields.redirect_funnel_id', $activeFunnels)
            ->setPrompt('--')
            ->setAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.redirect_funnel_id')
            ->setOption('id', 'redirect_funnel_id')
            ->setOption('description', 'sales_funnel.data.sales_funnels.description.redirect_funnel_id');

        $redirectFunnelId->getControlPrototype()->addAttributes(['class' => 'select2']);

        $isActive->addCondition(Form::EQUAL, false)
            ->toggle('redirect_funnel_id');

        $form->addText('url_key', 'sales_funnel.data.sales_funnels.fields.url_key')
            ->setAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.url_key')
            ->setRequired();

        $form->addCheckbox('only_logged', 'sales_funnel.data.sales_funnels.fields.only_logged');
        $form->addCheckbox('only_not_logged', 'sales_funnel.data.sales_funnels.fields.only_not_logged');

        $form->addSelect('segment_id', 'sales_funnel.data.sales_funnels.fields.segment', $this->segmentsRepository->all()->fetchPairs('id', 'name'))->setPrompt('--');

        $form->addText('start_at', 'sales_funnel.data.sales_funnels.fields.start_at')
            ->setAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.start_at');

        $form->addText('end_at', 'sales_funnel.data.sales_funnels.fields.end_at')
            ->setAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.end_at');

        $form->addTextArea('body', 'sales_funnel.data.sales_funnels.fields.body')
            ->setAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('head_meta', 'sales_funnel.data.sales_funnels.fields.head_meta')
            ->setAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('head_script', 'sales_funnel.data.sales_funnels.fields.head_script')
            ->setAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('no_access_html', 'sales_funnel.data.sales_funnels.fields.no_access_html')
            ->setAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('error_html', 'sales_funnel.data.sales_funnels.fields.error_html')
            ->setAttribute('data-codeeditor', 'htmlmixed');

        $form->addHidden('sales_funnel_id', $id);

        $form->setDefaults($defaults);

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $id = $values['sales_funnel_id'];
        unset($values['sales_funnel_id']);

        $segment = null;
        if ($values['segment_id']) {
            $segment = $this->segmentsRepository->find($values['segment_id']);
        }

        $startAt = null;
        if ($values['start_at'] && $values['start_at'] != '') {
            $startAt = DateTime::from(strtotime($values['start_at']));
            $values['start_at'] = $startAt;
        } else {
            $values['start_at'] = null;
        }
        $endAt = null;
        if ($values['end_at'] && $values['end_at'] != '') {
            $endAt = DateTime::from(strtotime($values['end_at']));
            $values['end_at'] = $endAt;
        } else {
            $values['end_at'] = null;
        }

        if ($values['is_active']) {
            $values['redirect_funnel_id'] = null;
        }

        if ($id) {
            $row = $this->salesFunnelsRepository->find($id);
            $this->salesFunnelsRepository->update($row, $values);
            $this->salesFunnelsCache->add($id, $values['url_key']);
            $this->onUpdate->__invoke($row);
        } else {
            $row = $this->salesFunnelsRepository->add(
                $values->name,
                $values->url_key,
                $values->body,
                $values['head_meta'] ?? null,
                $values['head_script'] ?? null,
                $startAt,
                $endAt,
                $values->is_active,
                $values->only_logged,
                $values->only_not_logged,
                $segment,
                $values->no_access_html,
                $values->error_html,
                $values->redirect_funnel_id
            );
            $this->salesFunnelsCache->add($row['id'], $values['url_key']);
            $this->onSave->__invoke($row);
        }
    }
}

<?php

namespace Crm\SalesFunnelModule\Forms;

use Contributte\Translation\Translator;
use Crm\SalesFunnelModule\Repository\SalesFunnelsMetaRepository;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Controls\TextInput;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SalesFunnelAdminFormFactory
{
    public $onUpdate;

    public $onSave;

    public function __construct(
        private SalesFunnelsRepository $salesFunnelsRepository,
        private SalesFunnelsMetaRepository $salesFunnelsMetaRepository,
        private SegmentsRepository $segmentsRepository,
        private Translator $translator
    ) {
    }

    public function create($id): Form
    {
        $defaults = [];
        $activeFunnels = [];

        if (isset($id)) {
            $funnel = $this->salesFunnelsRepository->find($id);
            $purchaseLimit['funnel_purchase_limit'] = $this->salesFunnelsMetaRepository->get($funnel, 'funnel_purchase_limit');
            $activeFunnels[(string) $funnel->redirect_funnel->id] = sprintf(
                "%s <small>%s</small>",
                $funnel->redirect_funnel->name,
                $funnel->redirect_funnel->url_key
            );

            $funnelData = $funnel->toArray();
            $defaults = array_merge($funnelData, $purchaseLimit);
        }

        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addText('name', 'sales_funnel.data.sales_funnels.fields.name')
            ->setHtmlAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.name')
            ->setRequired();

        $form->addTextArea('note', 'sales_funnel.data.sales_funnels.fields.note')
            ->setNullable()
            ->setHtmlAttribute('rows', 4)
            ->getControlPrototype()
            ->addAttributes(['class' => 'ace', 'data-lang' => 'text']);

        $isActive = $form->addCheckbox('is_active', 'sales_funnel.data.sales_funnels.fields.is_active');

        foreach ($this->salesFunnelsRepository->active()->fetchAll() as $f) {
            $activeFunnels[(string)$f->id] = "{$f->name} <small>({$f->url_key})</small>";
        }

        $redirectFunnelId = $form->addSelect('redirect_funnel_id', 'sales_funnel.data.sales_funnels.fields.redirect_funnel_id', $activeFunnels)
            ->setPrompt('--')
            ->setHtmlAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.redirect_funnel_id')
            ->setOption('id', 'redirect_funnel_id')
            ->setOption('description', 'sales_funnel.data.sales_funnels.description.redirect_funnel_id');

        $redirectFunnelId->getControlPrototype()->addAttributes(['class' => 'select2']);

        $isActive->addCondition(Form::EQUAL, false)
            ->toggle('redirect_funnel_id');

        $form->addText('url_key', 'sales_funnel.data.sales_funnels.fields.url_key')
            ->setHtmlAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.url_key')
            ->setRequired()
            ->addRule(function (TextInput $control) use (&$funnel) {
                $newValue = $control->getValue();
                if ($funnel && $funnel->url_key === $newValue) {
                    return true;
                }
                return $this->salesFunnelsRepository->findByUrlKey($newValue) === null;
            }, 'sales_funnel.admin.sales_funnels.copy.validation.url_key');

        $form->addCheckbox('only_logged', 'sales_funnel.data.sales_funnels.fields.only_logged');
        $form->addCheckbox('only_not_logged', 'sales_funnel.data.sales_funnels.fields.only_not_logged');

        $form->addSelect('segment_id', 'sales_funnel.data.sales_funnels.fields.segment', $this->segmentsRepository->all()->fetchPairs('id', 'name'))->setPrompt('--');

        $form->addText('start_at', 'sales_funnel.data.sales_funnels.fields.start_at')
            ->setHtmlAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.start_at');

        $form->addText('end_at', 'sales_funnel.data.sales_funnels.fields.end_at')
            ->setHtmlAttribute('placeholder', 'sales_funnel.data.sales_funnels.placeholder.end_at');

        $form->addInteger('limit_per_user', 'sales_funnel.data.sales_funnels.fields.limit_per_user')
            ->addCondition(Form::FILLED)
            ->addRule(Form::MIN, 'sales_funnel.data.sales_funnels.validation.minimum.limit_per_user', 1);

        $form->addInteger('funnel_purchase_limit', 'sales_funnel.data.sales_funnels.fields.funnel_purchase_limit')
            ->setNullable()
            ->addCondition(Form::FILLED)
            ->addRule(Form::MIN, 'sales_funnel.data.sales_funnels.validation.minimum.funnel_purchase_limit', 1);

        $form->addTextArea('body', 'sales_funnel.data.sales_funnels.fields.body')
            ->setHtmlAttribute('data-codeeditor', ['name' => 'twig', 'base' => 'text/html']);

        $form->addTextArea('head_meta', 'sales_funnel.data.sales_funnels.fields.head_meta')
            ->setHtmlAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('head_script', 'sales_funnel.data.sales_funnels.fields.head_script')
            ->setHtmlAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('no_access_html', 'sales_funnel.data.sales_funnels.fields.no_access_html')
            ->setHtmlAttribute('data-codeeditor', 'htmlmixed');

        $form->addTextArea('error_html', 'sales_funnel.data.sales_funnels.fields.error_html')
            ->setHtmlAttribute('data-codeeditor', 'htmlmixed');

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

        $meta = [
            'funnel_purchase_limit' => $values['funnel_purchase_limit'] ?? null
        ];
        unset($values['funnel_purchase_limit']);

        if ($values['is_active']) {
            $values['redirect_funnel_id'] = null;
        }

        if ($id) {
            $row = $this->salesFunnelsRepository->find($id);
            $this->salesFunnelsRepository->update($row, $values);
            $this->updateMeta($row, $meta);
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
                $values->redirect_funnel_id,
                $values->limit_per_user,
                $values->note
            );
            $this->updateMeta($row, $meta);
            $this->onSave->__invoke($row);
        }
    }

    private function updateMeta(ActiveRow $salesFunnel, array $meta): void
    {
        // null value will be deleted
        foreach ($meta as $name => $value) {
            $exists = $this->salesFunnelsMetaRepository->exists($salesFunnel, $name);

            if ($exists) {
                if ($value !== null) {
                    $this->salesFunnelsMetaRepository->updateValue($salesFunnel, $name, $value);
                } else {
                    $this->salesFunnelsMetaRepository->deleteValue($salesFunnel, $name);
                }
            } else {
                if ($value !== null) {
                    $this->salesFunnelsMetaRepository->add($salesFunnel, $name, $value);
                }
            }
        }
    }
}

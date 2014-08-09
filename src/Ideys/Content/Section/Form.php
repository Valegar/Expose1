<?php

namespace Ideys\Content\Section;

use Ideys\Content\Item;
use Ideys\Files\File;
use Ideys\String;
use Symfony\Component\Form as SfForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Form content manager.
 */
class Form extends Section implements SectionInterface
{
    /**
     * @var string
     */
    private $validationMessage;

    /**
     * {@inheritdoc}
     */
    public function getDefaultItems()
    {
        return $this->getFields();
    }

    /**
     * Get Field Items.
     *
     * @return array
     */
    public function getFields()
    {
        return $this->getItemsOfType(Item\Item::ITEM_FIELD);
    }

    /**
     * Test if Form has some Field Items.
     *
     * @return boolean
     */
    public function hasFields()
    {
        return $this->hasItemsOfType(Item\Item::ITEM_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function countMainItems()
    {
        return count($this->getFields());
    }

    /**
     * Return the form object with dynamic fields.
     *
     * @param \Symfony\Component\Form\FormFactory $formFactory
     *
     * @return \Symfony\Component\Form\Form
     */
    public function generateFormFields(SfForm\FormFactory $formFactory)
    {
        $form = $formFactory->createBuilder('form');

        foreach ($this->items as $item) {
            if (Item\Field::HTML == $item->category) {
                continue;
            }
            $fieldType = Item\Field::getSymfonyEquivalent($item->category);
            $options =  array(
                'label' => $item->title,
                'required' => (boolean) $item->required,
            );
            if ('choice' == $fieldType) {
                $choices = array_map('trim', explode("\n", $item->choices));
                $options += array(
                    'choices' => array_combine($choices, $choices),
                );
            }
            if (Item\Field::MULTIPLE_SELECT == $item->category) {
                $options += array(
                    'multiple' => true,
                );
            }
            if (Item\Field::RADIO == $item->category) {
                $options += array(
                    'expanded' => true,
                );
            }
            $form->add($item->slug, $fieldType, $options);
        }

        return $form->getForm();
    }

    /**
     * Return the form object with dynamic fields.
     *
     * @param  \Symfony\Component\HttpFoundation\Request $request
     * @param  \Symfony\Component\Form\Form              $form
     *
     * @return boolean true if form is submitted
     */
    public function checkSubmittedForm(Request $request, SfForm\Form $form)
    {
        $form->handleRequest($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $this->handleFiles($data);
            $this->db->insert('expose_form_result', array(
                'expose_section_id' => $this->id,
                'result' => serialize($data),
                'language' => $this->language,
                'date' => (new \DateTime())->format('Y-m-d H:i:s'),
            ));
            return true;
        }
        return false;
    }

    /**
     * Handle file data persistence.
     *
     * @param array $data
     */
    private function handleFiles(&$data)
    {
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $data[$key.'__file'] = String::slugify($value->getClientOriginalName());
                $data[$key.'__path'] = uniqid('expose').'.'.$value->guessClientExtension();
                $value->move(File::getDir(), $data[$key.'__path']);
                unset($data[$key]);
            }
        }
    }

    /**
     * Return form results.
     *
     * @return array
     */
    public function getResults()
    {
        $sql = 'SELECT r.* '.
               'FROM expose_form_result AS r '.
               'WHERE r.expose_section_id = ? '.
               'ORDER BY r.date ASC ';
        $results = $this->db->fetchAll($sql, array($this->id));

        foreach ($results as $row => $result) {
            $results[$row]['result'] = unserialize($result['result']);
        }

        return $results;
    }

    /**
     * Delete a form result.
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function deleteResult($id)
    {
        $rows = $this->db->delete('expose_form_result', array('id' => $id));

        return (0 < $rows);
    }

    /**
     * Delete a form and its result.
     *
     * @return boolean
     */
    public function delete()
    {
        $formDeleted = parent::delete();

        if ($formDeleted) {
            $this->db->delete('expose_form_result', array('expose_section_id' => $this->id));
        }

        return $formDeleted;
    }

    /**
     * @return string
     */
    public function getValidationMessage()
    {
        return $this->validationMessage;
    }

    /**
     * @param string $validationMessage
     *
     * @return Form
     */
    public function setValidationMessage($validationMessage)
    {
        $this->validationMessage = $validationMessage;

        return $this;
    }
}

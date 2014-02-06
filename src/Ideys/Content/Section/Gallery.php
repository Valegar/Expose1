<?php

namespace Ideys\Content\Section;

use Ideys\Content\ContentInterface;
use Symfony\Component\Form\FormFactory;

/**
 * Gallery content manager.
 */
class Gallery extends Section implements ContentInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getParameters()
    {
        return array(
            'gallery_mode' => 'slideshow',
            'thumb_list' => '0',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function settingsForm(FormFactory $formFactory)
    {
        $formBuilder = $this->settingsFormBuilder($formFactory)
            ->add('gallery_mode', 'choice', array(
                'label' => 'gallery.mode.mode',
                'choices' => static::getGalleryModeChoice(),
            ))
            ->add('thumb_list', 'choice', array(
                'label' => 'gallery.thumb.list.display',
                'choices' => \Ideys\Settings::getIOChoices(),
            ))
        ;

        return $formBuilder->getForm();
    }

    /**
     * Delete a selection of slides.
     *
     * @param integer  $sectionId
     * @param array    $itemIds
     * @return array
     */
    public function deleteSlides($sectionId, $itemIds)
    {
        $sectionItems = $this->findSectionItems($sectionId);
        $deletedIds = array();

        foreach ($itemIds as $id) {
            if (is_numeric($id)
                && $this->deleteItemAndRelatedFile($sectionItems[$id])) {
                $deletedIds[] = $id;
            }
        }
        return $deletedIds;
    }

    /**
     * Overwrite Content deleteSection method
     * to take into account pics deletion.
     *
     * @param integer $id
     */
    public function deleteSection($id)
    {
        $items = $this->findSectionItems($id);

        foreach ($items as $item) {
            $this->deleteItemAndRelatedFile($item);
        }

        return parent::deleteSection($id);
    }

    /**
     * Delete item's data entry and related files.
     *
     * @param array $item
     * @return boolean
     */
    private function deleteItemAndRelatedFile($item)
    {
        if (parent::deleteItem($item['id'])) {
            @unlink(WEB_DIR.'/gallery/'.$item['path']);
            @unlink(WEB_DIR.'/gallery/220/'.$item['path']);
            return true;
        }
        return false;
    }

    /**
     * Return gallery modes choices.
     *
     * @return array
     */
    public static function getGalleryModeChoice()
    {
        return array(
            'slideshow' => 'gallery.mode.slideshow',
            'fullscreen' => 'gallery.mode.slideshow.full.screen',
            'extended' => 'gallery.mode.slideshow.extended',
            'horizontal' => 'gallery.mode.horizontal',
            'vertical' => 'gallery.mode.vertical',
            'masonry' => 'gallery.mode.masonry',
        );
    }
}
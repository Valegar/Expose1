<?php

namespace Ideys\Content;

use Ideys\Content\Section\Provider\SectionProvider;
use Ideys\Content\Section\Entity\SectionInterface;
use Ideys\Content\Section\Entity\Section;
use Ideys\Content\Item\Entity\Item;
use Silex\Application;

/**
 * App content manager.
 */
class ContentFactory
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var string
     */
    protected $language = 'en';

    /**
     * Constructor: inject required Silex dependencies.
     *
     * @param \Silex\Application $app
     */
    public function __construct(Application $app)
    {
        $this->db = $app['db'];
        $this->security = $app['security'];
    }

    /**
     * Replace sections keys replacement for composite sections.
     *
     * - Gallery integration
     * - Video integration
     *
     * @param SectionInterface  $section
     * @param \Twig_Environment $twig
     */
    public function composeSectionItems(SectionInterface $section, \Twig_Environment $twig)
    {
        if ($section->isComposite()) {

            $sectionProvider = new SectionProvider($this->db, $this->security);
            $sectionProvider->setLanguage($this->language);

            $items = $section->getDefaultItems();

            // A: extract replacement keys
            $sectionSlugs = array();
            $galleries = array();
            foreach ($items as $item) {
                if ($item instanceof Item) {
                    $content = $item->getContent();
                    $countMatch = preg_match_all('/__(slides|video):([\w\@-]+)__/', $content, $matches);
                    if ((int)$countMatch > 0) {
                        $keys = $matches[0];
                        $contentType = $matches[1];
                        foreach ($matches[2] as $row => $slug) {
                            $sectionSlugs[$contentType[$row]][$keys[$row]] = $slug;
                        }
                        $galleries = $sectionSlugs['slides'];
                    }
                }
            }

            // B: retrieve related sections
            $replacementValues = array();
            if (!empty($galleries)) {
                $sanitizedSlugs = filter_var_array($galleries, FILTER_SANITIZE_STRING);
                $sql = SectionProvider::baseQuery()
                    . 'WHERE s.slug IN (\''. implode("', '", $sanitizedSlugs) .'\') '
                    . 'AND t.language = ? '
                    . "AND s.type IN ('gallery', 'channel') ";

                $sectionsToInclude = $this->db->fetchAll($sql, array($this->language));

                $replacementStrings = array_flip($galleries);
                foreach ($sectionsToInclude as $s) {
                    $sectionToInclude = $sectionProvider->hydrateSection($s);
                    $replacementValues[$replacementStrings[$sectionToInclude->getSlug()]] = $sectionToInclude;
                }
            }

            // C: replace keys by sections content
            foreach ($items as $item) {
                if ($item instanceof Item) {
                    $content = $item->getContent();

                    // Insert extracted contents
                    foreach ($replacementValues as $key => $replacementSection) {
                        $replacementTemplate = $twig->render('frontend/'.$replacementSection->getType().'/_embed.html.twig', array(
                            'section' => $replacementSection,
                        ));
                        $content = str_replace($key, $replacementTemplate, $content);
                    }

                    // Remove no replaced keys
                    foreach ($galleries as $key => $slug) {
                        $content = str_replace($key, '', $content);
                    }

                    $item->setContent($content);
                }
            }
        }
    }

    /**
     * Update all sections common parameters with identical tag.
     *
     * @param Section $section
     */
    public function updateGroupedSections(Section $section)
    {
        $this->db->update(TABLE_PREFIX.'section', array(
            'custom_css' => $section->getCustomCss(),
            'custom_js' => $section->getCustomJs(),
            'shuffle' => $section->getShuffle(),
        ),
        array('tag' => $section->getTag(), 'type' => $section->getType()));

        // Update translated sections parameters
        $sectionsIds = $this->db->fetchAll(
              'SELECT id FROM '.TABLE_PREFIX.'section '
            . 'WHERE tag = ? AND type = ?',
            array($section->getTag(), $section->getType())
        );

        foreach ($sectionsIds as $id) {
            $this->db->update(TABLE_PREFIX.'section_trans', array(
                'parameters' => serialize($section->getParameters()),
            ), array('expose_section_id' => $id['id'], 'language' => $this->language));
        }
    }
}

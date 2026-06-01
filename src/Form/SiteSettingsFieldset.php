<?php declare(strict_types=1);

namespace IiifServer\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Players'; // @translate

    protected $elementGroups = [
        'iiif_server' => 'IIIF Server', // @translate
        'player' => 'Players', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'iiif-server')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'iiifserver_manifest_link_dialog',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'iiif_server',
                    'label' => 'Resource block IIIF manifest link button: Dialog content', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline-block;',
                    ],
                    'info' => 'The button is always drag-and-droppable. On click, a share dialog is shown with the selected sections.', // @translate
                    'value_options' => [
                        'button_label' => 'Display the label "IIIF manifest" next to the icon in the button', // @translate
                        'what_is_iiif' => 'Display "What is IIIF?" link', // @translate
                        'copy_on_click' => 'Copy manifest url to clipboard on click and display the confirmation', // @translate
                        'copy_button' => 'Display copy and paste message with a copy button', // @translate
                        'drag_icon' => 'Display drag-and-drop message with IIIF icon', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_manifest_link_dialog',
                ],
            ])

            ->add([
                'name' => 'iiifserver_player',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player: Viewer', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'diva' => 'Diva (module)', // @translate
                        'mirador' => 'Mirador (module)', // @translate
                        'mirador_core' => 'Mirador (Omeka)', // @translate
                        'openseadragon' => 'OpenSeadragon', // @translate
                        'universalviewer' => 'Universal Viewer (module)', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_player',
                ],
            ])
            ->add([
                'name' => 'iiifserver_player_osd_sidebar',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player: OpenSeadragon thumbnails sidebar', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        'bottom' => 'Bottom', // @translate
                        'top' => 'Top', // @translate
                        'left' => 'Left', // @translate
                        'right' => 'Right', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_osd_sidebar',
                ],
            ])
            ->add([
                'name' => 'iiifserver_player_inline_height',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Viewer: Inline height', // @translate
                    'info' => 'CSS length (e.g. 600px, 80vh, 100%).', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_inline_height',
                    'placeholder' => '600px',
                ],
            ])
            ->add([
                'name' => 'iiifserver_player_button_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Viewer Button: Label', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_button_label',
                ],
            ])
            ->add([
                'name' => 'iiifserver_player_button_lazy',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'Resource block IIIF Player Button: Lazy load', // @translate
                    'info' => 'When enabled, the viewer is loaded only on the first click. The options may conflict with a pre-existing instance of the same viewer on the page.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_button_lazy',
                ],
            ])
            ->add([
                'name' => 'iiifserver_player_osd_show_zoom',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'player',
                    'label' => 'OpenSeadragon viewer: Display the current zoom percentage', // @translate
                    'info' => 'Shows a small overlay with the zoom level (100 % = fit to viewport). The position and visibility behavior can be customized via theme CSS.', // @translate
                ],
                'attributes' => [
                    'id' => 'iiifserver_player_osd_show_zoom',
                ],
            ])
        ;
    }
}

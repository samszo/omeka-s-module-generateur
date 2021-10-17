/*
 * Copyright Daniel Berthereau, 2017-2020
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

$(document).ready(function() {

    /**
     * Main simple search.
     */
    var searchGenerations = `<input type="radio" name="resource-type" id="search-generation" value="generation" data-input-placeholder="${Omeka.jsTranslate('Search generations')}" data-action="${searchGenerationsUrl}">
    <label for="search-generation">${Omeka.jsTranslate('Generations')}</label>`;
    $('#search-form #advanced-options').append(searchGenerations);

    /**
     * Advanced search
     *
     * Adapted from Omeka application/asset/js/advanced-search.js.
     */
    var values = $('#datetime-queries .value');
    var index = values.length;
    $('#datetime-queries').on('o:value-created', '.value', function(e) {
        var value = $(this);
        value.children(':input').attr('name', function () {
            return this.name.replace(/\[\d\]/, '[' + index + ']');
        });
        index++;
    });

    /**
     * Search sidebar.
     */
    $('#content').on('click', 'a.search', function(e) {
        e.preventDefault();
        var sidebar = $('#sidebar-search');
        Omeka.openSidebar(sidebar);

        // Auto-close if other sidebar opened
        $('body').one('o:sidebar-opened', '.sidebar', function () {
            if (!sidebar.is(this)) {
                Omeka.closeSidebar(sidebar);
            }
        });
    });

    /**
     * Better display of big generations.
     */
    if ( $.isFunction($.fn.webuiPopover) ) {
        $('a.popover').webuiPopover('destroy').webuiPopover({
            placement: 'auto-bottom',
            content: function (element) {
                var target = $('[data-target=' + element.id + ']');
                var content = target.closest('.webui-popover-parent').find('.webui-popover-current');
                $(content).removeClass('truncate').show();
                return content;
            },
            title: '',
            arrow: false,
            backdrop: true,
            onShow: function(element) { element.css({left: 0}); }
        });

        $('a.popover').webuiPopover();
    }

    /**
     * Append an generation sub-form to the resource template form.
     *
     * @todo Allows Omeka to append a form element via triggers in Laminas form or js.
     * @see Omeka resource-template-form.js
     */

    var propertyList = $('#resourcetemplateform #properties');

    /**
     * Because chosen is used, only the value is available, not the term.
     *
     * @param int id
     * @return string|null
     */
    var resourceClassTerm = function(termId) {
        return termId
            ? $('#resourcetemplateform select[name="o:resource_class[o:id]"] option[value=' + termId + ']').data('term')
            : null;
    }

    var generationInfo = function() {
        return `
    <br />
    <div id="generation-info">
        <h3>${Omeka.jsTranslate('Web Open Generation')}</h3>
        <p>
            ${Omeka.jsTranslate('With the class <code>oa:Generation</code>, it’s important to choose the part of the generation to which the property is attached:')}
            ${Omeka.jsTranslate('It can be the generation itself (default), but the body or the target too.')}
        </p>
        <p>${Omeka.jsTranslate('For example, to add an indication on a uncertainty of  a highlighted segment, the property should be attached to the target, but the description of a link should be attached to the body.')}</p>
        <p>${Omeka.jsTranslate('Standard non-ambivalent properties are automatically managed.')}</p>
    </div>`;
    }

    // Template of  the generation sub-form (application/view/omeka/admin/resource-template/form.phtml).
    var generationPartInput = function(propertyId, generationPart) {
        generationPart = generationPart || 'oa:Generation';
        return `<input class="generation-part" type="hidden" name="o:resource_template_property[${propertyId}][data][generation_part]" value="${generationPart}">`;
    }
    var generationPartForm = function(generationPart) {
        var checked_2 = (generationPart === 'oa:hasBody') ? 'checked="checked" ' : '';
        var checked_3 = (generationPart === 'oa:hasTarget') ? 'checked="checked" ' : '';
        var checked_1 = (checked_2 === '' && checked_3 === '') ? 'checked="checked" ' : '';
        var html = `
    <div id="generation-options" class="field">
        <h3>${Omeka.jsTranslate('Generation')}</h3>
        <div id="generation-part" class="option">
            <label for="generation-part">${Omeka.jsTranslate('Generation part')}</label>
            <span>${Omeka.jsTranslate('To comply with Generation data model, select the part of the generation this property will belong to.')}</span>
            <span><i>${Omeka.jsTranslate('This option cannot be imported/exported currently.')}</i></span><br />
            <input type="radio" name="generation_part" ${checked_1}value="oa:Generation" /> ${Omeka.jsTranslate('Generation')}<br />
            <input type="radio" name="generation_part" ${checked_2}value="oa:hasBody" /> ${Omeka.jsTranslate('Generation body')}<br />
            <input type="radio" name="generation_part" ${checked_3}value="oa:hasTarget" /> ${Omeka.jsTranslate('Generation target')}
        </div>
    </div>`;
        return html;
    }

    // Initialization during load.
    if (resourceClassTerm($('#resourcetemplateform select[name="o:resource_class[o:id]"]').val()) === 'oa:Generation') {
        // Set hidden params inside the form for each properties of  the resource template.
        var addNewPropertyRowUrl = propertyList.data('addNewPropertyRowUrl')
        var baseUrl = addNewPropertyRowUrl.split('?')[0];
        var resourceTemplateId = baseUrl.split('/')[baseUrl.split('/').length - 2];
        baseUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
        baseUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
        baseUrl = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
        var resourceTemplateDataUrl = baseUrl + '/generation/resource-template-data';
        $.get(resourceTemplateDataUrl, {resource_template_id: resourceTemplateId})
            .done(function(data) {
                propertyList.find('li.property').each(function() {
                    var propertyId = $(this).data('property-id');
                    var generationPart = data[propertyId] || '';
                    $(this).find('.data-type').after(generationPartInput(propertyId, generationPart));
                });
            });
        // Initialization of the sidebar.
        $('#edit-sidebar .confirm-main').append(generationPartForm());
        $('#content').append(generationInfo());
    }

    // Set/unset the sub-form when the class oa:Generation is selected.
    $(document).on('change', '#resourcetemplateform select[name="o:resource_class[o:id]"]', function(evt, params) {
        var termId = $('#resourcetemplateform select[name="o:resource_class[o:id]"]').val();
        var term = resourceClassTerm(termId);
        if (term === 'oa:Generation') {
            $('#edit-sidebar .confirm-main').append(generationPartForm());
            $('#content').append(generationInfo());
        } else {
            $('#generation-options').remove();
            $('#generation-info').remove();
        }
    });

    $('#property-selector .selector-child').click(function(e) {
        e.preventDefault();
        var propertyId = $(this).closest('li').data('property-id');
        if ($('#properties li[data-property-id="' + propertyId + '"]').length) {
            // Resource templates cannot be assigned duplicate properties.
            return;
        }
        propertyList.find('li:last-child').append(generationPartInput(propertyId));
    });

    propertyList.on('click', '.property-edit', function(e) {
        e.preventDefault();
        var prop = $(this).closest('.property');
        var generationPart = prop.find('.generation-part');
        var generationPartVal = generationPart.val() || 'oa:Generation';
        $('#generation-part input[name=generation_part][value="' + generationPartVal + '"]').prop('checked', true)
            .trigger("click");

        // Save the value for the current property (the other values are managed by resource-template-form.js).
        $('#set-changes').on('click.setchanges', function(e) {
            generationPart.val($('#generation-part input[name="generation_part"]:checked').val());
        });
    });

});

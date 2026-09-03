/**
 * JavaScript for form editing Once met conditions.
 *
 * @module     moodle-availability_oncemet-form
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// eslint-disable-next-line camelcase
M.availability_oncemet = M.availability_oncemet || {};

/**
 * @class M.availability_oncemet.form
 * @extends M.core_availability.plugin
 */
M.availability_oncemet.form = Y.Object(M.core_availability.plugin);

/**
 * Identifiers of the stored Once met restrictions of the edited item which hold unlocks already.
 *
 * Filled from PHP, see \availability_oncemet\frontend::get_javascript_init_params().
 *
 * @property unlockedInstances
 * @type Array
 */
M.availability_oncemet.form.unlockedInstances = [];

/**
 * URL of the unlock report of each stored Once met restriction of the edited item, keyed by instance id.
 *
 * Filled from PHP, see \availability_oncemet\frontend::get_report_urls().
 *
 * @property reportUrls
 * @type Object
 */
M.availability_oncemet.form.reportUrls = {};

/**
 * Whether the click handler which asks before an unlocked restriction is removed is in place.
 *
 * @property removeConfirmationBound
 * @type Boolean
 */
M.availability_oncemet.form.removeConfirmationBound = false;

/**
 * Gets the default nested restriction structure.
 *
 * @return {Object}
 */
M.availability_oncemet.form.getDefaultChildJson = function() {
    return {op: '&', c: []};
};

/**
 * Takes over the identifiers of the restrictions which hold unlocks and starts guarding their removal.
 *
 * @param {Array} unlockedinstances Identifiers of the stored restrictions which hold unlocks.
 * @param {Object} reporturls Unlock report URL of each stored restriction, keyed by instance id.
 */
M.availability_oncemet.form.initInner = function(unlockedinstances, reporturls) {
    this.unlockedInstances = unlockedinstances || [];
    this.reportUrls = reporturls || {};
    this.bindRemoveConfirmation();
};

/*
 * The uuidV4Bytes(), uuidV4String() and uuidV4() helpers below are taken from TinyMCE 8.2.2, which
 * ships with Moodle core in lib/editor/tiny/js/tinymce/tinymce.js.
 *
 * Copyright (c) 2024, Ephox Corporation DBA Tiny Technologies, Inc.
 * Licensed under the terms of the GNU General Public License Version 2 or later.
 * See lib/editor/tiny/js/tinymce/license.md and https://github.com/tinymce/tinymce.
 *
 * They are duplicated here because TinyMCE does not expose them, and they are needed as
 * crypto.randomUUID() is only available in secure contexts, which sites that are still served over
 * plain HTTP are not.
 *
 * The logic and the comments are unchanged, but the code had to be rewritten from ES6 to ES5, as
 * Moodle disables ES6+ for YUI modules in its ESLint configuration.
 *
 * Bit operations have to be allowed twice below. ESLint checks the source of a YUI module, while
 * Shifter runs JSHint over the built module afterwards, and the two do not read each other's
 * directives. Leaving either of them out means a lint error in one of the two runs.
 *
 * This attribution comment is deliberately the only declaration of the snippet's origin, there is
 * no thirdpartylibs.xml entry for it. A <location> there names a file or a directory which is
 * third-party code as a whole, and the only path we could name is this file, of which the snippet
 * is a small part. Declaring it would drop the whole file out of ESLint, Grunt and the code
 * checker, as moodle-plugin-ci excludes every declared location from its file list, and it would
 * make the admin third-party libraries page claim that this plugin bundles TinyMCE, which it does
 * not. Moodle core handles adapted snippets the same way, see the code taken from
 * https://github.com/alphp/strftime in lib/classes/date.php, which is not declared either.
 */
/* eslint-disable no-bitwise */
/* jshint bitwise:false */
var uuidV4Bytes = function() {
    var bytes = window.crypto.getRandomValues(new Uint8Array(16));
    // https://tools.ietf.org/html/rfc4122#section-4.1.3
    // This will first bit mask away the most significant 4 bits (version octet)
    // then mask in the v4 number we only care about v4 random version at this point so (byte & 0b00001111 | 0b01000000)
    bytes[6] = bytes[6] & 15 | 64;
    // https://tools.ietf.org/html/rfc4122#section-4.1.1
    // This will first bit mask away the highest two bits then masks in the highest bit so (byte & 0b00111111 | 0b10000000)
    // So it will set the Msb0=1 & Msb1=0 described by the "The variant specified in this document." row in the table
    bytes[8] = bytes[8] & 63 | 128;
    return bytes;
};
/* jshint bitwise:true */
/* eslint-enable no-bitwise */
var uuidV4String = function() {
    var uuid = uuidV4Bytes();
    var getHexRange = function(startIndex, endIndex) {
        var buff = '';
        var i;
        for (i = startIndex; i <= endIndex; ++i) {
            var hexByte = uuid[i].toString(16).padStart(2, '0');
            buff += hexByte;
        }
        return buff;
    };
    // RFC 4122 UUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
    return getHexRange(0, 3) + '-' + getHexRange(4, 5) + '-' + getHexRange(6, 7) + '-' +
            getHexRange(8, 9) + '-' + getHexRange(10, 15);
};

/**
 * Generate a uuidv4 string
 * In accordance with RFC 4122 (https://datatracker.ietf.org/doc/html/rfc4122)
 */
var uuidV4 = function() {
    if (window.isSecureContext) {
        return window.crypto.randomUUID();
    } else {
        return uuidV4String();
    }
};
/* End of the code which is taken from TinyMCE. */

/**
 * Generates a stable instance identifier for a new Once met block as a random UUID (version 4).
 *
 * @return {String}
 */
M.availability_oncemet.form.generateInstanceId = function() {
    return uuidV4();
};

/**
 * Finds a nested Item or List object by its root YUI node.
 *
 * @param {M.core_availability.List} list Parent list to search.
 * @param {Y.Node} node Node to find.
 * @return {M.core_availability.Item|M.core_availability.List|null}
 */
M.availability_oncemet.form.findChildByNode = function(list, node) {
    var i, child, found;

    for (i = 0; i < list.children.length; i++) {
        child = list.children[i];
        if (child.node === node) {
            return child;
        }
        if (child instanceof M.core_availability.List) {
            found = M.availability_oncemet.form.findChildByNode(child, node);
            if (found) {
                return found;
            }
        }
    }

    return null;
};

/**
 * Routes delete clicks inside a Once met nested list to the correct list object.
 *
 * Core availability always deletes via the root list, which cannot see nested
 * conditions rendered inside plugin controls.
 *
 * @param {Y.Node} node Once met plugin root node.
 * @param {M.core_availability.List} childList Nested restriction list.
 */
M.availability_oncemet.form.bindNestedDeletes = function(node, childList) {
    if (node.getData('oncemetDeleteBound')) {
        return;
    }
    node.setData('oncemetDeleteBound', true);

    var container = node.one('.availability-oncemet-children');
    var dom = container.getDOMNode();

    dom.addEventListener('click', function(e) {
        var deleteLink = e.target.closest ? e.target.closest('.availability-delete') : null;
        var itemElement, listElement, toDelete;

        if (!deleteLink || !dom.contains(deleteLink)) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        itemElement = deleteLink.closest('.availability-item');
        if (itemElement && dom.contains(itemElement)) {
            toDelete = M.availability_oncemet.form.findChildByNode(childList, Y.one(itemElement));
        } else {
            listElement = deleteLink.closest('.availability-list');
            if (listElement && dom.contains(listElement) && listElement !== childList.node.getDOMNode()) {
                toDelete = M.availability_oncemet.form.findChildByNode(childList, Y.one(listElement));
            }
        }

        if (toDelete) {
            childList.deleteDescendant(toDelete);
            M.core_availability.form.rootList.renumber();
        }
    }, true);
};

/**
 * Starts asking for confirmation before a Once met restriction which holds unlocks is removed.
 *
 * The delete icons of the form are built by core, which offers no hook to ask anything before it
 * acts on them, and the plugin does not even own the icon which removes one of its own blocks: that
 * icon belongs to the item which wraps the block. The click is therefore caught on its way down to
 * whichever handler would carry the removal out, which is the one place where all of them can be
 * held back at once.
 */
M.availability_oncemet.form.bindRemoveConfirmation = function() {
    if (this.removeConfirmationBound) {
        return;
    }
    this.removeConfirmationBound = true;

    document.addEventListener('click', function(e) {
        M.availability_oncemet.form.confirmRemoval(e);
    }, true);
};

/**
 * Holds a removal click back until the teacher has confirmed that the unlocks may go with it.
 *
 * @param {Event} e Click event on its way down to the delete handlers.
 */
M.availability_oncemet.form.confirmRemoval = function(e) {
    var removeLink, removed;

    removeLink = e.target.closest ? e.target.closest('.availability-delete') : null;
    if (!removeLink) {
        return;
    }

    // The click which the confirmation sends off again has to reach the delete handlers untouched.
    if (removeLink.hasAttribute('data-oncemet-confirmed')) {
        removeLink.removeAttribute('data-oncemet-confirmed');
        return;
    }

    // Core hangs the delete icon of a condition next to the plugin controls and the one of a
    // restriction set into its "None" placeholder, so whichever of the two encloses the icon more
    // closely is what the click is about to remove.
    removed = removeLink.closest('.availability-item, .availability-list');
    if (!removed || M.availability_oncemet.form.getUnlockedInstances(removed).length === 0) {
        return;
    }

    // Nothing may act on this click before the teacher has answered, neither the delete handler of
    // core nor the one which this plugin binds for its nested restrictions.
    e.preventDefault();
    e.stopImmediatePropagation();

    // M.util.show_confirm_dialog() would be the usual way to ask this, but it only forwards a custom
    // title and dialogtype to core/notification as of Moodle 5.2 (MDL-87281). Calling
    // core/notification directly keeps the delete-styled dialog with its own title on Moodle 5.1 too.
    require(['core/notification'], function(Notification) {
        Notification.deleteCancelPromise(
            M.util.get_string('confirmremove_title', 'availability_oncemet'),
            M.util.get_string('confirmremove_message', 'availability_oncemet'),
            M.util.get_string('confirmremove_continue', 'availability_oncemet')
        ).then(function() {
            // Repeat the very click which was held back, this time letting it pass.
            removeLink.setAttribute('data-oncemet-confirmed', '1');
            removeLink.click();
            return;
        }).catch(function() {
            // The teacher cancelled the removal.
            return;
        });
    });
};

/**
 * Finds the Once met restrictions within an element which would lose unlocks if it was removed.
 *
 * Removing an element removes everything inside it, so a restriction set and a Once met block both
 * take whatever is nested within them along, which is why this looks at the whole subtree.
 *
 * @param {Element} element Element which is about to be removed.
 * @return {Array} Identifiers of the restrictions which hold unlocks, empty if there are none.
 */
M.availability_oncemet.form.getUnlockedInstances = function(element) {
    var blocks, i, instanceid;
    var found = [];

    blocks = element.querySelectorAll('.availability_oncemet[data-oncemet-instanceid]');
    for (i = 0; i < blocks.length; i++) {
        instanceid = blocks[i].getAttribute('data-oncemet-instanceid');
        if (this.unlockedInstances.indexOf(instanceid) !== -1 && found.indexOf(instanceid) === -1) {
            found.push(instanceid);
        }
    }

    return found;
};

/**
 * Builds the button which leads to the unlock report of a stored Once met restriction.
 *
 * A restriction which the teacher has just added within the open form is not stored yet and has no
 * report to offer, and neither has one whose unlocks the teacher may not see.
 *
 * @param {String} instanceid Identifier of the Once met restriction.
 * @return {Y.Node|null} Button node, null if there is no report to link to.
 */
M.availability_oncemet.form.getReportButton = function(instanceid) {
    var button;
    var url = this.reportUrls[instanceid];

    if (!url) {
        return null;
    }

    button = Y.Node.create('<a class="btn btn-sm btn-secondary"></a>');
    button.setAttribute('href', url);
    button.set('text', M.util.get_string('unlocks_button', 'availability_oncemet'));

    return button;
};

M.availability_oncemet.form.getNode = function(json) {
    var childjson, childList, description, help, instanceid, node, reportbutton;

    // The instance id has to be kept on the node, as there is only one plugin object for all
    // Once met blocks of a form. Keeping it on the plugin object would mean that all blocks of
    // a form end up sharing the instance id of whichever block was rendered last.
    instanceid = (json && json.instanceid) ? json.instanceid : this.generateInstanceId();
    childjson = (json && json.c) ? json.c : this.getDefaultChildJson();
    childList = new M.core_availability.List(childjson, false, false);
    description = M.util.get_string('addrestriction', 'availability_oncemet');
    help = M.util.get_string('helptext_persistent', 'availability_oncemet') + ' ' +
            M.util.get_string('helptext_remove', 'availability_oncemet');
    node = Y.Node.create(
        '<div class="availability_oncemet availability-group">' +
        '<p class="mb-2">' + description + '</p>' +
        '<p class="mb-2 text-muted small">' + help + '</p>' +
        '<div class="availability-oncemet-report mb-2"></div>' +
        '<div class="availability-oncemet-children"></div>' +
        '</div>'
    );
    reportbutton = this.getReportButton(instanceid);
    if (reportbutton) {
        node.one('.availability-oncemet-report').appendChild(reportbutton);
    }
    node.one('.availability-oncemet-children').appendChild(childList.node);
    node.setData('oncemetChildList', childList);
    node.setData('oncemetInstanceId', instanceid);

    // The removal confirmation reaches a block through the DOM rather than through the plugin
    // objects, as it starts from a click on an icon which belongs to core, so the identifier has to
    // be readable from the element itself.
    node.setAttribute('data-oncemet-instanceid', instanceid);

    this.bindNestedDeletes(node, childList);
    return node;
};

M.availability_oncemet.form.fillValue = function(value, node) {
    var childList = node.getData('oncemetChildList');

    value.type = 'oncemet';
    value.c = childList.getValue();
    value.instanceid = node.getData('oncemetInstanceId');
};

M.availability_oncemet.form.fillErrors = function(errors, node) {
    var childList = node.getData('oncemetChildList');

    if (childList.children.length === 0) {
        errors.push('availability_oncemet:error_nochildren');
    }
    childList.fillErrors(errors);
};

M.availability_oncemet.form.focusAfterAdd = function(node) {
    var button = node.one('.availability-button button');
    if (button) {
        button.focus();
    }
};

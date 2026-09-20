/**
 * FormRender — the one walk over a form's fields.
 *
 * 🔑 WHY THIS FILE EXISTS. A form is drawn in three places, each with its own
 * markup and its own per-type switch:
 *
 *   forms/fill.php              the analyst filler   (.form-field, error divs)
 *   self-service/catalogue.php  the portal           (.cat-field, id="fN")
 *   forms/edit/index.php        the builder preview  (.preview-field, disabled)
 *
 * ⚠️ THEIR MARKUP IS NOT MERGED, AND MUST NOT BE. `form-logic.js` already makes
 * that argument and it is right: the three genuinely look different, and one
 * function emitting all three would change how every existing form renders for
 * no benefit. What they must not each own is the *walk* — the order fields come
 * in, the width each one gets, and what happens to a type nobody taught them.
 *
 * 🔴 THE FAILURE THIS EXISTS TO STOP. Before this file, an unhandled field type
 * did three different silent things:
 *
 *   filler   no `default:` at all      -> the question VANISHED from the form
 *   portal   `default:` rendered text  -> a text box pretending to be the control,
 *                                         accepting and storing a wrong answer
 *   preview  `default: return ''`      -> the question VANISHED from the preview
 *
 * All three are silent, and the portal's is the worst: it takes an answer. A
 * surface now returns null for a type it cannot draw and this file renders a
 * visible, non-submittable notice and logs to the console. Loud, in one place.
 *
 * Depends on: FormLogic (widths), window.t (i18n).
 */
(function (global) {
    'use strict';

    /**
     * Walk a form's fields and build its markup.
     *
     * @param {Array}  fields   the form's fields, in order
     * @param {Object} surface  { name, field(f, ctx) }
     *                          `field` returns the markup for ONE field, or null
     *                          if this surface cannot draw that type.
     *                          ctx = { width, wrapAttrs }
     * @returns {string} html
     */
    function render(fields, surface) {
        if (!surface || typeof surface.field !== 'function') {
            throw new Error('FormRender.render: surface must provide field()');
        }
        var name = surface.name || 'unnamed surface';
        var html = '';

        (fields || []).forEach(function (f) {
            /* The width, in twelfths, on the wrapper every surface already uses —
               one decision covering every field type present and future. Absent
               means full width, which is every field predating the layout work. */
            var width = global.FormLogic ? global.FormLogic.fieldWidth(f) : 12;

            /* data-wrap-id is deliberately separate from data-field-id, which the
               value-reading code uses with two different meanings (on the input
               for simple types, on the wrapper for groups). Visibility only ever
               looks for data-wrap-id. */
            var ctx = {
                width: width,
                wrapAttrs: 'data-wrap-id="' + f.id + '" data-width="' + width + '"'
            };

            var markup = surface.field(f, ctx);
            if (markup === null || markup === undefined || markup === '') {
                html += unknown(f, ctx, name);
                return;
            }
            html += markup;
        });

        return html;
    }

    /**
     * What a surface shows for a field type it cannot draw.
     *
     * 🔴 Deliberately NOT an input. The portal used to fall back to a text box,
     * which looks like a working question and stores whatever is typed into it
     * as that field's answer. Showing nothing is bad; showing something that
     * takes a wrong answer is worse. This renders a notice, carries the wrap
     * attributes so conditional visibility still governs it, and shouts.
     */
    function unknown(field, ctx, surfaceName) {
        if (global.console && console.error) {
            console.error('FormRender: ' + surfaceName + ' cannot draw field type "'
                + (field && field.field_type) + '" (field ' + (field && field.id) + ')');
        }
        var label = '';
        if (field && field.label) {
            label = String(field.label).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }
        var msg = (global.t ? global.t('forms.render.unsupported_type')
                            : 'This question cannot be shown here.');
        if (msg === 'forms.render.unsupported_type') msg = 'This question cannot be shown here.';
        return '<div class="form-field-unsupported" ' + ctx.wrapAttrs + '>'
             + (label ? '<strong>' + label + '</strong> ' : '')
             + '<span>' + msg + '</span></div>';
    }

    global.FormRender = { render: render };
})(window);

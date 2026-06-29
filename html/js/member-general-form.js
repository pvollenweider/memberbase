/**
 * Alpine.js component for the member general data inline edit form.
 * Loaded as an external file to comply with CSP 'self' restrictions.
 *
 * Data is passed via data-* attributes on the root element:
 *   data-member-id, data-initial (JSON), data-gender-labels (JSON)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
function memberGeneralForm() {
    return {
        memberId:     0,
        editing:      false,
        saving:       false,
        saved:        false,
        error:        null,
        data:         {},
        draft:        {},
        genderLabels: {},

        init() {
            this.memberId     = parseInt(this.$el.dataset.memberId || '0', 10);
            this.data         = JSON.parse(this.$el.dataset.initial      || '{}');
            this.genderLabels = JSON.parse(this.$el.dataset.genderLabels || '{}');
            this.draft        = { ...this.data };
        },

        startEdit() {
            this.draft   = { ...this.data };
            this.editing = true;
            this.saved   = false;
            this.error   = null;
            this.$nextTick(function () {
                var el = document.getElementById('tiptap-comment');
                if (el && el._tt) el._tt.commands.setContent(this.data.comment || '');
            }.bind(this));
        },

        cancel() {
            this.editing = false;
            this.error   = null;
        },

        formatDate(iso) {
            if (!iso) return '';
            var parts = iso.split('-');
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        },

        async save() {
            var ta = document.getElementById('comment');
            if (ta) this.draft.comment = ta.value;
            this.saving = true;
            this.error  = null;
            try {
                var resp = await fetch('/api/members/' + this.memberId, {
                    method:  'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(this.draft),
                });
                var body = await resp.json();
                if (!resp.ok) {
                    this.error = body.error || 'Erreur lors de la sauvegarde';
                    return;
                }
                var d = body.data;
                this.data = {
                    lastName:  d.lastName  || '',
                    firstName: d.firstName || '',
                    society:   d.society   || '',
                    gender:    d.gender    || 'na',
                    title:     d.title     || '',
                    address:   d.address   || '',
                    npa:       d.npa       || '',
                    email:     d.email     || '',
                    web:       d.web       || '',
                    telProf:   d.telProf   || '',
                    tel:       d.tel       || '',
                    portable:  d.portable  || '',
                    fax:       d.fax       || '',
                    birthDate: d.birthDate || '',
                    comment:   d.comment   || '',
                };
                this.editing = false;
                this.saved   = true;
            } catch (e) {
                this.error = 'Erreur réseau';
            } finally {
                this.saving = false;
            }
        },
    };
}

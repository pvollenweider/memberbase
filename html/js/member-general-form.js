function memberGeneralForm() {
    return {
        editing: false,
        startEdit() { this.editing = true; },
        cancel()    { this.editing = false; },
    };
}

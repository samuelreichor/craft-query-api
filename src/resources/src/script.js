function initUserPermissions(wrapper) {
  const selectAllBtn = wrapper.querySelector('.select-all');
  const allCheckboxes = wrapper.querySelectorAll('input[type="checkbox"]');
  const checkboxesToToggle = Array.from(allCheckboxes).filter(cb => !cb.classList.contains('select-all'));

  function toggleSelectAll(e) {
    if (e.target.checked) {
      checkboxesToToggle
          .filter(cb => !cb.checked)
          .forEach(cb => cb.click());
      selectAllBtn.checked = true;
    } else {
      checkboxesToToggle
          .filter(cb => cb.checked)
          .forEach(cb => cb.click());
      selectAllBtn.checked = false;
    }
  }

  function toggleCheckbox(e) {
    const checkbox = e.currentTarget;

    // uncheck select all btn if target checkboxes are unchecked
    if (selectAllBtn.checked === true && checkbox.checked === false) {
      selectAllBtn.checked = false;
    }
    if (checkbox.disabled) {
      e.preventDefault();
    }
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click',(e) => toggleSelectAll(e));

    if (selectAllBtn.checked) {
      checkboxesToToggle
          .filter(cb => !cb.checked)
          .forEach(cb => cb.click());
    }
  }
  checkboxesToToggle.forEach(checkbox => {
    checkbox.addEventListener('click', toggleCheckbox);
  });
}

document.querySelectorAll('.user-permissions').forEach(wrapper => {
  initUserPermissions(wrapper);
});


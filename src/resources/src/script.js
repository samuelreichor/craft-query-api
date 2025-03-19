function craftTranslate(category, message) {
  if (typeof Craft !== 'undefined' && Craft.t) {
    return Craft.t(category, message);
  }
  return message;
}

function initUserPermissions(wrapper) {
  const selectAllBtn = wrapper.querySelector('.select-all');
  const allCheckboxes = wrapper.querySelectorAll('input[type="checkbox"]:not(.group-permission)');

  function canSelectAll() {
    return Array.from(allCheckboxes).some(cb => !cb.checked);
  }

  function updateSelectAllBtn() {
    if (!selectAllBtn) return;
    if (canSelectAll()) {
      selectAllBtn.textContent = craftTranslate('app', 'Select All');
    } else {
      selectAllBtn.textContent = craftTranslate('app', 'Deselect All');
    }
  }

  function toggleSelectAll(ev) {
    ev.preventDefault();
    if (canSelectAll()) {
      Array.from(allCheckboxes)
          .filter(cb => !cb.checked)
          .forEach(cb => cb.click());
    } else {
      Array.from(allCheckboxes)
          .filter(cb => cb.checked)
          .forEach(cb => cb.click());
    }
  }

  function toggleCheckbox(ev) {
    const checkbox = ev.currentTarget;
    if (checkbox.disabled) {
      ev.preventDefault();
      return;
    }

    const listItem = checkbox.closest('li');
    if (!listItem) return;

    const nested = listItem.querySelectorAll(
        ':scope > ul > li > input[type="checkbox"]:not(.group-permission)'
    );

    if (checkbox.checked) {
      nested.forEach(childCb => childCb.disabled = false);
    } else {
      nested.forEach(childCb => {
        if (childCb.checked) {
          childCb.click();
        }
        childCb.disabled = true;
      });
    }

    updateSelectAllBtn();
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', toggleSelectAll);
  }
  allCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('click', toggleCheckbox);
  });

  updateSelectAllBtn();
}

document.querySelectorAll('.user-permissions').forEach(wrapper => {
  initUserPermissions(wrapper);
});


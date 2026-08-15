(function () {
  const form = document.getElementById('enquiry-form');
  const msg = document.getElementById('form-msg');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    msg.textContent = '';
    const data = new FormData(form);

    try {
      const res = await fetch(form.action, { method: 'POST', body: data });
      const result = await res.json();
      if (result.success) {
        msg.style.color = '#0a8a3f';
        msg.textContent = document.documentElement.getAttribute('data-lang') === 'en'
          ? 'Thank you. We will contact you shortly.'
          : 'धन्यवाद. आम्ही लवकरच संपर्क करू.';
        form.reset();
      } else {
        throw new Error(result.error || 'failed');
      }
    } catch (err) {
      msg.style.color = '#b91c1c';
      msg.textContent = document.documentElement.getAttribute('data-lang') === 'en'
        ? 'Something went wrong. Please WhatsApp or call us instead.'
        : 'काहीतरी चूक झाली. कृपया WhatsApp किंवा कॉल करा.';
    }
  });
})();

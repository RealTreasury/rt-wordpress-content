function shareOnLinkedIn() {
  const url = encodeURIComponent(window.location.href);
  const title = encodeURIComponent(document.title);
  const summary = encodeURIComponent(document.querySelector('meta[name="description"]')?.content || '');
  const linkedinUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}&title=${title}&summary=${summary}`;
  window.open(linkedinUrl, '_blank', 'width=600,height=400');
}

function shareOnX() {
  const url = encodeURIComponent(window.location.href);
  const text = encodeURIComponent(document.title);
  const hashtags = encodeURIComponent('Treasury,RealEstate');
  const xUrl = `https://twitter.com/intent/tweet?url=${url}&text=${text}&hashtags=${hashtags}`;
  window.open(xUrl, '_blank', 'width=600,height=400');
}

function shareViaEmail() {
  const subject = encodeURIComponent(document.title);
  const body = encodeURIComponent(`I thought you might enjoy this article:\n\n${document.title}\n\n${window.location.href}`);
  window.location.href = `mailto:?subject=${subject}&body=${body}`;
}

function copyLink(button) {
  const url = window.location.href;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(() => showCopySuccess(button));
  } else {
    const textArea = document.createElement('textarea');
    textArea.value = url;
    textArea.style.position = 'fixed';
    textArea.style.top = 0;
    textArea.style.left = 0;
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand('copy');
      showCopySuccess(button);
    } finally {
      document.body.removeChild(textArea);
    }
  }
}

function showCopySuccess(button) {
  if (!button) return;
  const originalText = button.textContent || '';
  button.textContent = 'Copied!';
  setTimeout(() => { button.textContent = originalText; }, 2000);
}

function previewImage(input, targetId){
  const el = document.getElementById(targetId);
  if(!input.files || !input.files[0]) return;
  const file = input.files[0];
  const url = URL.createObjectURL(file);
  el.src = url;
  el.onload = () => URL.revokeObjectURL(url);
}

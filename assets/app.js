document.addEventListener('change', function(e){
  if(e.target.matches('[name="motivo"]')){
    const box=document.querySelector('#vacuna-fields');
    if(box) box.hidden=e.target.value!=='Vacuna';
  }
  if(e.target.matches('[name="medicamento_id"]')){
    const box=document.querySelector('#otro-medicamento');
    if(box) box.hidden=e.target.value!=='otro';
  }
});
document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{
  if(!confirm(el.dataset.confirm)) e.preventDefault();
}));
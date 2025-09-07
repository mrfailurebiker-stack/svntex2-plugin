// Registration + OTP Frontend Logic
(function($){
  const form = $('#svntex2RegForm');
  const msg = form.find('.form-messages');
  const sendBtn = $('#svntex2SendOtp');

  function flash(type,text){
    msg.removeClass('error success').addClass(type).html(text);
  }

  sendBtn.on('click', function(){
    const mobile = form.find('input[name="mobile"]').val().trim();
    if(mobile.length < 10){ flash('error','Enter valid mobile first'); return; }
    sendBtn.prop('disabled', true).text('Sending...');
    $.post(SVNTEX2Auth.ajax_url, { action:'svntex2_send_otp', nonce:SVNTEX2Auth.nonce, mobile }, function(r){
      if(r.success){ flash('success', r.data.message); } else { flash('error', r.data.message || 'OTP failed'); }
    }).always(()=> sendBtn.prop('disabled', false).text('Send OTP'));
  });

  form.on('submit', function(e){
    e.preventDefault();
    flash('', '');
    const data = form.serializeArray().reduce((a,x)=>{a[x.name]=x.value;return a;},{ action:'svntex2_register', nonce:SVNTEX2Auth.nonce });
    $.post(SVNTEX2Auth.ajax_url, data, function(r){
      if(r.success){ flash('success', 'Registered! ID: '+ r.data.customer_id); form.trigger('reset'); }
      else { const errs = (r.data.errors||[]).join('<br>') || 'Failed'; flash('error', errs); }
    });
  });
})(jQuery);

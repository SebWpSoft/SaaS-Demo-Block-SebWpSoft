( function(blocks, element, components, i18n, blockEditor){
  const { registerBlockType } = blocks;
  const { createElement: el, Fragment, useState } = element;
  const { TextControl, TextareaControl, Button, Notice, PanelBody } = components;
  const { __ } = i18n;
  const { InspectorControls, URLInput } = blockEditor;

  registerBlockType('sdb/demo-card', {
    title: __('SaaS Demo Card','saas-demo-block'),
    icon: 'megaphone',
    category: 'widgets',
    supports: { html: false },
    edit: (props)=>{
      const { attributes, setAttributes } = props;
      const [checking, setChecking] = useState(false);
      const [msg, setMsg] = useState('');
      const featuresText = (attributes.features || []).join('\n');

      const doPing = async ()=>{
        if (!attributes.ctaUrl) return;
        setChecking(true); setMsg('');
        try{
          const base = (window.SDB && SDB.pingUrl) ? SDB.pingUrl : '/wp-json/sdb/v1/ping';
          const res  = await fetch(base + '?url=' + encodeURIComponent(attributes.ctaUrl), {
            headers: { 'X-WP-Nonce': (window.SDB ? SDB.nonce : '') }
          });
          const data = await res.json();
          const status = data && data.status ? data.status : 'unknown';
          setAttributes({ status });
          setMsg(status === 'online' ? __('URL online ✅','saas-demo-block') : __('URL offline ❌','saas-demo-block'));
        } catch(e){
          setAttributes({ status: 'unknown' });
          setMsg(__('Error while checking.','saas-demo-block'));
        } finally {
          setChecking(false);
        }
      };

      return el(Fragment, {},
        el(InspectorControls, {},
          el(PanelBody, { title: __('Card Settings','saas-demo-block'), initialOpen: true },
            el(TextControl, { label: __('Name','saas-demo-block'), value: attributes.name, onChange: (v)=> setAttributes({ name: v }) }),
            el(TextareaControl, { label: __('Tagline','saas-demo-block'), value: attributes.tagline, onChange: (v)=> setAttributes({ tagline: v }) }),
            el(TextControl, { label: __('Price','saas-demo-block'), value: attributes.price, onChange: (v)=> setAttributes({ price: v }) }),
            el(TextControl, { label: __('CTA Label','saas-demo-block'), value: attributes.ctaLabel, onChange: (v)=> setAttributes({ ctaLabel: v }) }),
            el('div', { style:{marginBottom:'8px'} },
              el('label', {}, __('CTA URL','saas-demo-block')),
              el(URLInput, { value: attributes.ctaUrl, onChange: (v)=> setAttributes({ ctaUrl: v }) })
            ),
            el(TextareaControl, {
              label: __('Features (one per line)','saas-demo-block'),
              value: featuresText,
              onChange: (v)=> setAttributes({ features: (v||'').split(/\n+/).map(s=>s.trim()).filter(Boolean) })
            }),
            el(Button, { isPrimary: true, onClick: doPing, disabled: checking }, checking ? __('Checking…','saas-demo-block') : __('Check URL','saas-demo-block')),
            msg ? el(Notice, { status: 'info', isDismissible: false }, msg) : null
          )
        ),
        el('div', { className: 'sdb-card sdb-card--editor' },
          el('h3', {}, attributes.name || __('SaaS Name','saas-demo-block')),
          el('p', {}, attributes.tagline || __('Your product tagline','saas-demo-block')),
          el('ul', {}, (attributes.features||[]).map((f,i)=> el('li', { key:i }, f))),
          el('div', {}, [
            el('strong', { key: 'price' }, attributes.price || ''), ' ',
            (attributes.ctaUrl && attributes.ctaLabel) ? el('a', { key: 'cta', href: attributes.ctaUrl, target: '_blank', rel: 'noopener' }, attributes.ctaLabel) : null
          ])
        )
      );
    },
    save: ()=> null // dynamic render in PHP
  });
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n, window.wp.blockEditor );

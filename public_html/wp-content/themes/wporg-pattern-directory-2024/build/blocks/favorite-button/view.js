import*as t from"@wordpress/interactivity";var e={d:(t,o)=>{for(var r in o)e.o(o,r)&&!e.o(t,r)&&Object.defineProperty(t,r,{enumerable:!0,get:o[r]})},o:(t,e)=>Object.prototype.hasOwnProperty.call(t,e)};const o=(r={getContext:()=>t.getContext,store:()=>t.store},a={},e.d(a,r),a);var r,a;(0,o.store)("wporg/patterns/favorite-button",{state:{get labelScreenReader(){const{label:t,count:e}=(0,o.getContext)();return t.screenReader.replace("%s",e)},get labelCount(){const{count:t}=(0,o.getContext)();return`(${t})`},get labelAction(){const{label:t,isFavorite:e}=(0,o.getContext)();return e?t.remove:t.add}},actions:{*triggerAction(){const t=(0,o.getContext)();if(t.isFavorite)try{const e=yield wp.apiFetch({path:"/wporg/v1/pattern-favorites",method:"DELETE",data:{id:t.postId}});t.isFavorite=!1,t.count=e}catch(t){}else try{const e=yield wp.apiFetch({path:"/wporg/v1/pattern-favorites",method:"POST",data:{id:t.postId}});t.isFavorite=!0,t.count=e}catch(t){}}}});
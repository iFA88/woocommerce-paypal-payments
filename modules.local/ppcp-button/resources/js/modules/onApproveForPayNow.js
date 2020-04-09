const onApprove = (context) => {
    return (data, actions) => {
        return fetch(context.config.ajax.approve_order.endpoint, {
            method: 'POST',
            body: JSON.stringify({
                nonce: context.config.ajax.approve_order.nonce,
                order_id:data.orderID
            })
        }).then((res)=>{
            return res.json();
        }).then((data)=>{
            if (!data.success) {
                //Todo: Error handling
                return;
            }
            document.querySelector('#place_order').click()
        });

    }
}

export default onApprove;
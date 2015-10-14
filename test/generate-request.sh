#!/bin/sh

for i in `seq 1 $1`
  do cat <<EOT > /vagrant/resources/purchase/deal_pack/K1Kiso-3/aaa$i@bbb.ccc_purchase_request.yml
---
user_id: aaa@bbb.ccc
pack_id: K1Kiso-3
date_requested: 2015-10-04 23:38:16+09:00
EOT
done
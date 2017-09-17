#!/bin/bash

# Update Repositories & instal ansible
sudo apt-get install -y software-properties-common
sudo apt-add-repository ppa:ansible/ansible
sudo apt-get update
sudo apt-get install -y ansible

sudo mkdir -p /etc/ansible
printf '[vagrant]\nlocalhost\n' | sudo tee /etc/ansible/hosts > /dev/null

echo "Running provisioner: ansible"
PYTHONUNBUFFERED=1 ansible-playbook -c local /data/ansible/vagrant.provision.yml
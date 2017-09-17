# Don't touch unless you know what you're doing!

Vagrant.require_version ">= 1.8"

Vagrant.configure(2) do |config|

  # Base Box
  # --------------------
  # geerlingguy/ubuntu1604 - ubuntu 16.04 (64) with port fixes
  # ubuntu/trusty64        - ubuntu 14.04 (64)
  config.vm.box = "geerlingguy/ubuntu1604"

  # Optional (Remove if desired)
  config.vm.provider :virtualbox do |v|
    
    # The name that appears in the VirtualBox GUI
    # -----------------------------------
    v.name = "quedis"

    # How much RAM to give the VM (in MB)
    # -----------------------------------
    v.customize ["modifyvm", :id, "--memory", "1024"]
    v.customize ["modifyvm", :id, "--cpus", "1"]
    v.customize ["modifyvm", :id, "--ioapic", "on"]

  end
  
  # Connect to IP
  # Note: Use an IP that doesn't conflict with any OS's DHCP (Below is a safe bet)
  # --------------------
  config.vm.hostname = "quedis"
  config.vm.network :private_network, ip: "192.168.50.72"
  config.ssh.forward_agent = true


  # Synced Folder
  # --------------------
  config.vm.synced_folder ".", "/data", :mount_options => [ 'dmode=775', 'fmode=764' ], :owner => 'vagrant', :group => 'www-data'

  # Provisioning Script (inside VM)
  # --------------------
  config.vm.provision "shell", path: "ansible/vagrant.sh"

  if Vagrant.has_plugin?('vagrant-cachier')
    config.cache.scope = :box
  end

end

Vagrant.configure(2) do |config|
  config.vm.box = "bento/ubuntu-16.04"

  config.vm.box_check_update = false

  config.vm.network "forwarded_port", guest: 8080, host: 31335

  config.vm.provider "virtualbox" do |vb|
     vb.memory = "1024"
     vb.cpus = 4
  end

  config.vm.provision "install_packages", type: "shell", path: "build/vagrant/install_packages.sh"
  config.vm.provision "install_composer", type: "shell", path: "build/vagrant/install_composer.sh"

end

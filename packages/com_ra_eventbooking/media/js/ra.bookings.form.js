/* 
 * copyright: Chris Vaughan
 * email: ruby.tuesday@ramblers-webs.org.uk
 * EW     an RA event or walk in ramblers library format
 * ESC    a collection of booking records , EVB
 * EVB    a booking record for an event,  an object
 * NBI    a new booking information for one user
 * BLC    a collection of bookings, collection of BLI
 * BLI    the user information booking for a user
 * WLC    a collection of waiting/notify records, collection of WLI
 * WLI    the user information about someone on waiting/notify list
 */

var ra;
if (typeof (ra) === "undefined") {
    ra = {};
}

if (typeof (ra.bookings) === "undefined") {
    ra.bookings = {};
}

ra.bookings.formBooking = function (user, ewid, ew, evb, ics) {
    this.user = user;
    this.ewid = ewid;
    this.ew = ew;
    this.ics = ics;
    this.eventTitle = ra.date.dowddmmyyyy(ew.basics.walkDate) + ' ' + ew.basics.title;
    this.evb = evb;
    this.elements = null;
    this.verification = {md5: '',
        codeLength: 6};
    this.bookingData = {attendees: 1,
        id: 0,
        name: '',
        email: '',
        confirmEmail: '',
        telephone: '',
        md5Email: '',
        member: 'No',
        paid: '',
        currentAttendees: 0};
    this.input = new ra.bookings.inputFields;
    const BookingStatus = Object.freeze({
        BOOKED: 0,
        INVALIDLOGON: 1,
        INVALIDLOGOFF: 2,
        NONE: 3,
        WAITING: 4
    });
    this.display = function () {
        // this.tag;
        //   this.tag.innerHTML = '';
        var tags = [
            {name: 'container', parent: 'root', tag: 'div', attrs: {class: 'ra bookings form'}},
            {name: 'event', parent: 'container', tag: 'div', attrs: {class: 'bookingitem'}},
            {name: 'eventDetails', parent: 'event', tag: 'div'},
            {name: 'lists', parent: 'event', tag: 'div'},
            {name: 'userDetails', parent: 'container', tag: 'div', attrs: {class: 'bookingitem'}},
            {name: 'user', parent: 'userDetails', tag: 'div'},
            //  {name: 'status', parent: 'userDetails', tag: 'div'},
            {name: 'bookplace', parent: 'userDetails', tag: 'div'}
        ];
        this.tag = document.createElement("div");
        this.tag.style.display = "inline-block";
        this.formModal = ra.modals.createModal(this.tag, false);
        this.elements = ra.html.generateTags(this.tag, tags);
        this.displayEvent(this.elements.eventDetails);
        var self = this;
        this.tag.addEventListener('userDetailsVerified', (e) => {
            self.elements.lists.innerHTML = '';
            self.elements.bookplace.innerHTML = '';
            self.bookingForm(self.elements.bookplace);
            self.emailBookingContact(self.elements.lists);
            self.elements.lists.appendChild(document.createElement('p'));
            self.bookingLists(self.elements.lists);
        });
        this.tag.addEventListener('userDetailsChanged', (e) => {
            self.elements.lists.innerHTML = '';
            self.elements.bookplace.innerHTML = '';
            if (e.raData.okay) {
                self.verifyEmailAddress(self.elements.bookplace);
            }
        });
        this.getUser(this.elements.user);
    };
    this.emailBookingContact = function (tag) {
        var ele = document.createElement('div');
        tag.appendChild(ele);
        var help = document.createElement("span");
        help.style.paddingRight = '10px';
        help.innerHTML = 'Need help? Email the booking contact (' + this.evb.options.booking_contact_name + ')';
        ele.appendChild(help);
        ra.bookings.displayEmailIcon(ele, help.innerHTML, tag, "emailContact", {user: self});
        var self = this;
        tag.addEventListener('emailContact', (e) => {
            var options = {
                subject: self.eventTitle,
                toWhom: self.evb.options.booking_contact_name + ' (the booking contact for this event)',
                from: this.bookingData,
                emailContent: '',
                ewid: self.ewid,
                serverAction: 'emailBookingContact'
            };
            self.emailForm(options);
        });
    };
    this.refreshDisplay = function () {
        let event = new Event("bookingInfoChanged"); // 
        document.dispatchEvent(event);
        this.formModal.close();
    };
    this.displayEvent = function (tag) {
        ra.bookings.addTextTag(tag, 'h3', ra.date.dowddmmyyyy(ew.basics.walkDate));
        ra.bookings.addTextTag(tag, 'h3', this.ew.basics.title);

        this.evb.displayBookingStatus(tag, this.user);
        this.evb.displayUserInfo(tag, this.user.id);
    };
    this.getUser = function (tag) {
        var memOptions = {
            'Yes': "Yes - I am a member",
            'No': 'No - I have not joined yet'
        };
        if (this.user.id > 0) {
            this.bookingData.id = this.user.id;
            this.bookingData.name = this.user.name;
            this.bookingData.md5Email = this.user.md5Email;
            this.bookingData.member = 'Yes';
            ra.bookings.addTextTag(tag, 'h3', 'Welcome ' + this.user.name);
            let event = new Event("userDetailsVerified"); // 
            this.tag.dispatchEvent(event);
            return;
        }
        if (!this.evb.options.guest) {
            ra.bookings.addTextTag(tag, 'p', 'Logged on: You must be logged to book places.');
            return;
        }
        ra.bookings.addTextTag(tag, "h2", "Booking place(s) on this event");
        ra.bookings.addTextTag(tag, 'p', 'Welcome - you are not logged on to this site and hence your booking will be as a guest');
        ra.bookings.addTextTag(tag, 'p', 'Please provide the following contact information');
        ra.bookings.addTextTag(tag, 'p', '<small>Note that your name may be visible to other users, so you may wish to abbreviate it, e.g. Jane S rather than Jane Smith, Other details may be viewed by the groups booking contacts</small>');
        this._name = this.input.addText(tag, 'name', "Your name:", this.bookingData, 'name', 'Who is making this booking', null);
        this._email = this.input.addEmail(tag, 'email', "Email Address:", this.bookingData, 'email', 'Contact\'s email address', null);
        this._confirmEmail = this.input.addEmail(tag, 'email', "Confirm Email Address:", this.bookingData, 'confirmEmail', 'Confirm email address', null);
        this._telephone = this.input.addText(tag, 'telephone', "Telephone number:", this.bookingData, 'telephone', 'Contact\'s telephone/mobile number', null);
        if (this.evb.options.telephone_required) {
            ra.bookings.addTextTag(tag, 'p', '<small>Telephone number must be in one of the following formats 0xxx xxx xxxx,  0xxxx xxxxxx or  0xxxx xxxxxx</small>');
        } else {
            ra.bookings.addTextTag(tag, 'p', 'Optional <small>telephone number must be in one of the following formats 0xxx xxx xxxx,  0xxxx xxxxxx or  0xxxx xxxxxx</small>');
        }

        this._member = this.input.addSelect(tag, 'member', 'Are you a Ramblers member', memOptions, this.bookingData, 'member');
        ra.bookings.addTextTag(tag, 'p', '<small>Non members are welcome to join groups for three walks, after which they are expected to join the Ramblers</small>');
        var self = this;
        this._name.addEventListener("input", function () {
            self.checkUserDetails();
        });
        this._email.addEventListener("input", function () {
            self.checkUserDetails();
        });
        this._confirmEmail.addEventListener("input", function () {
            self.checkUserDetails();
        });
        this._telephone.addEventListener("input", function () {
            self.checkUserDetails();
        });

    };
    this.checkUserDetails = function () {
        var $okay = true;
        this._name.style.color = 'black';
        this._email.style.color = 'black';
        this._confirmEmail.style.color = 'black';
        this._telephone.style.color = 'black';

        var name = this._name.value.trim();
        var email = this._email.value.trim();
        var confirmEmail = this._confirmEmail.value.trim();

        if (email !== confirmEmail) {
            this.verification = {md5: '',
                codeLength: 6};
        }
        if (name.length < 3) {
            this._name.style.color = 'red';
            $okay = false;
        }
        if (email.length < 1) {
            this._email.style.color = 'red';
            $okay = false;
        }
        if (!this._email.checkValidity()) {
            this._email.style.color = 'red';
            $okay = false;
        }
        this.bookingData.md5Email = md5(email);
        if (confirmEmail !== email) {
            this._confirmEmail.style.color = 'red';
            $okay = false;
        }

        // Landlines: 0xxx xxx xxxx  OR  0xxxx xxxxxx
        // Mobiles:   07xxx xxxxxx
        if (this.evb.options.telephone_required) {
            const re = /^(0\d{3}\s?\d{3}\s?\d{4}|0\d{4}\s?\d{6}|07\d{3}\s?\d{6})$/;
            if (!re.test(this.bookingData.telephone)) {
                this._telephone.style.color = 'red';
                $okay = false;
            }
        }
        //function isUkPhone(str) {
        //     return re.test(str);
        //  }

        let event = new Event("userDetailsChanged"); // 
        event.raData = {};
        event.raData.okay = $okay;
        this.tag.dispatchEvent(event);

    };
    this.verifyEmailAddress = function (tag) {
        // msg 
        // send/resend button
        // send email to user with verification code
        // receive md5 value of code sent
        // display input field and message
        // if md5(input) is same as above then email verified 
        var self = this;
        var tags = [
            {parent: 'root', tag: 'hr'},
            {name: 'div', parent: 'root', tag: 'div', attrs: {class: 'ra bookings verify'}},
            {name: 'message', parent: 'div', tag: 'div', innerHTML: 'We need to send you a Code so you can verify your email address.<br/>If you cannot find the email please look in your spam folder<br/>Please enter the supplied code in the field below'},
            {name: 'send', parent: 'div', tag: 'div', attrs: {class: 'link-button tiny button mintcake'}, innerHTML: 'Send Email', style: {'margin-top': '10px'}},
            {name: 'save', parent: 'div', tag: 'div', style: {'margin-top': '10px'}},
            {name: 'code', parent: 'div', tag: 'div', style: {'margin-top': '10px'}}
        ];
        var options = {
            never: "Never",
            session: "For this session",
            '24hours': 'For 24 hours',
            '1week': 'For 1 week'
        };
        var saveObj = {
            period: 'session'
        };

        var md5Email = ra.cookie.read("ra-booking");
        if (this.bookingData.md5Email === md5Email) {
            let event = new Event("userDetailsVerified"); // 
            event.raData = {};
            event.raData.okay = true;
            this.tag.dispatchEvent(event);
            return;
        }
        var elements = ra.html.generateTags(tag, tags);
        var saveTag = this.input.addSelect(elements.save, '', 'Save your contact details', options, saveObj, 'period', "How long to save you details so you don't need to verify your email", null);

        var codeTag = document.createElement('input');
        codeTag.setAttribute('class', 'booking email code');
        codeTag.setAttribute('type', 'text');
        codeTag.setAttribute('placeholder', 'Enter code');
        elements.code.appendChild(codeTag);
        elements.send.addEventListener('click', (e) => {
            elements.send.innerHTML = "Resend Email";
            var data = {
                user: self.bookingData,
                ewid: self.ewid
            };
            var sa = new ra.bookings.queryServer(this, 'VerifyEmail');
            sa.action(data, (self, results) => {
                self.verification.md5 = results.data.md5code;
                self.verification.codeLength = results.data.codelength;

            });

        });
        codeTag.addEventListener('input', (e) => {
            var input = e.target.value.trim();
            if (input.length === self.verification.codeLength) {
                if (self.verification.md5 === md5(input)) {
                    var days = -1;
                    switch (saveObj.period) {
                        case 'never':
                            days = -1;
                            break;
                        case 'session':
                            days = 0;
                            break;
                        case '24hours':
                            days = 1;
                            break;
                        case '1week':
                            days = 7;
                            break;
                    }

                    ra.cookie.create(this.bookingData.md5Email, "ra-booking", days);
                    let event = new Event("userDetailsVerified"); // 
                    event.raData = {};
                    event.raData.okay = true;
                    this.tag.dispatchEvent(event);
                }
            }
        });
    };

    this.bookingLists = function (tag) {
        var self = this;
        tag.addEventListener('AdminEmailAllBooking', (e) => {
            var options = {
                subject: self.eventTitle,
                toWhom: 'All those booked on event',
                from: self.user,
                emailContent: '',
                to: null,
                ewid: self.ewid,
                serverAction: 'AdminEmailAllBooking'
            };
            self.emailForm(options);
        });
        tag.addEventListener('emailSingleBooker', (e) => {
            var options = {
                subject: self.eventTitle,
                toWhom: e.raData.user.name,
                from: self.user,
                emailContent: '',
                to: e.raData.user,
                ewid: self.ewid,
                serverAction: 'Adminemailsinglebooking'
            };
            self.emailForm(options);
        });
        tag.addEventListener('AdminEmailBookingList', (e) => {
            var data = {
                user: self.user,
                ewid: self.ewid
            };
//          
            var sa = new ra.bookings.queryServer(this, 'AdminEmailBookingList');
            sa.action(data, (self, results) => {

            });

        });
        tag.addEventListener('changePaid', (e) => {
            let amount = ra.showPrompt("Please enter amount paid, enter blank if not paid", "Zero");
            if (amount !== null) {
                self.changePaid(e.raData.md5Email, amount);
            }
        });
        tag.addEventListener('deleteBooker', (e) => {
            if (ra.showConfirm('Confirm you wish to delete this booking (NO EMAIL IS SENT TO USER)')) {
                self.deleteBooker(e.raData);
            }
        });
        tag.addEventListener('AdminEmailAllWaiting', (e) => {
            var options = {
                subject: self.eventTitle,
                toWhom: 'All those on notification list',
                from: self.user,
                emailContent: '',
                to: null,
                ewid: self.ewid,
                serverAction: 'AdminEmailAllWaiting'
            };
            self.emailForm(options);
        });
        tag.addEventListener('AdminEmailSingleWaiting', (e) => {
            var options = {
                subject: self.eventTitle,
                toWhom: e.raData.user.name,
                from: self.user,
                emailContent: '',
                to: e.raData.user,
                ewid: self.ewid,
                serverAction: 'AdminEmailSingleWaiting'
            };
            self.emailForm(options);
        });
        tag.addEventListener('deleteWaiting', (e) => {
            if (ra.showConfirm('Confirm you wish to delete this person from waiting list(NO EMAIL IS SENT TO USER)')) {
                self.deleteWaiting(e.raData);
            }
        });
        switch (this.getStatus()) {
            case BookingStatus.WAITING:
            case BookingStatus.BOOKED:
            case BookingStatus.NONE:
                if (this.evb.canDisplayBookingList(this.user)) {
                    this.evb.listAttendees(tag, this.user);
                }
                if (this.evb.canDisplayWaitingList(this.user)) {
                    this.evb.listWaiting(tag, this.user);
                }
                if (this.user.canEdit) {
                    var email = document.createElement("div");
                    email.innerHTML = "<i>Email booking list/waiting list to me&nbsp;&nbsp;</i>";
                    email.style.color = '#8A2716';
                    tag.appendChild(email);
                    ra.bookings.displayEmailIcon(email, "Email booking list to me", tag, "AdminEmailBookingList");
                }
        }
    };
    this.getStatus = function () {
        this.bookingItem = this.evb.getBooking(this.bookingData.md5Email);
        if (this.bookingItem !== null) {
            this.bookingData.currentAttendees = this.bookingItem.attendees;
            if (this.bookingItem.id === this.user.id) {
                return BookingStatus.BOOKED;
            } else {
                if (this.user.id === 0) {
                    return BookingStatus.INVALIDLOGON;
                } else {
                    return BookingStatus.INVALIDLOGOFF;
                }
            }
        }
        var waitingItem = this.evb.getWaiting(this.bookingData.md5Email);
        if (waitingItem !== null) {
            return BookingStatus.WAITING;
        }
        return BookingStatus.NONE;
    };
    this.displayStatus = function (tag) {
        // display status of user booking
        switch (this.getStatus()) {
            case BookingStatus.WAITING:
                ra.bookings.addTextTag(tag, "div", "You are on the waiting list");
                break;
            case BookingStatus.BOOKED:
                ra.bookings.addTextTag(tag, "div", "<b>You have booked " + this.bookingItem.attendees + " place(s)</b>");
                if (this.evb.options.payment_required) {
                    ra.bookings.addTextTag(tag, "div", "Payment made <b>" + this.bookingItem.paid + "</b>");
                    this.bookingData.paid = this.bookingItem.paid;
                }
                break;
            case BookingStatus.INVALIDLOGOFF:
                ra.bookings.addTextTag(tag, "div", "You already have a booking: Your booking was made while a guest user (not logged on), please log off to alter your booking");
                break;
            case BookingStatus.INVALIDLOGON:
                ra.bookings.addTextTag(tag, "div", "You already have a booking: Your booking was made while logged on, please log on to alter your booking");
                break;
            case BookingStatus.NONE:
                ra.bookings.addTextTag(tag, "div", "Your booking: You don't have any existing booking.");
                break;
            default:
                ra.bookings.addTextTag(tag, "div", "Your booking: ERROR: INVALID BOOKING STATUS");
                return;
        }
    };
    this.bookingForm = function (tag) {
        var self = this;
        switch (this.getStatus()) {
            case BookingStatus.WAITING:
            case BookingStatus.BOOKED:
            case BookingStatus.NONE:
                this.displayStatus(tag);
                break;
            case BookingStatus.INVALIDLOGON:
            case BookingStatus.INVALIDLOGOFF:
                this.displayStatus(tag);
                return;
            default:
                return;
        }

        var maxPlaces = this.evb.calcMaxPlaces(this.user);
        if (this.bookingItem !== null) {
            var ele = document.createElement('div');
            tag.appendChild(ele);
            var del = document.createElement("span");
            del.style.paddingRight = '10px';
            del.innerHTML = 'Cancel booking';
            ele.appendChild(del);
            ra.bookings.displayDeleteIcon(ele, "Cancel this booking", tag, "deleteBooking", {user: self});
            tag.addEventListener('deleteBooking', (e) => {
                if (ra.showConfirm('Confirm you wish to cancel booking')) {
                    self.bookingData.attendees = 0;
                    self.submitBooking();
                }
            });
        }

        var container = document.createElement("span");
        container.classList.add('submit');
        tag.appendChild(container);
        var range = {min: 1, max: maxPlaces, current: 0};
        var prompt = "No of places you wish to book";
        if (this.bookingItem !== null) {
            if (maxPlaces === 0) {
                range.max = this.bookingItem.attendees;
            }
            range.current = this.bookingItem.attendees;
            prompt = "Change number places you wish to book";
        }
        if (!this.evb.bookingClosed()) {
            var select = this.input.addNumberSelect(container, 'attendees', prompt, this.bookingData, 'attendees', range, null);
            if (select !== null) {
                var submit = this.input.addButton(container, ['link-button', 'tiny', 'button', 'mintcake'], 'Submit');
                submit.addEventListener('click', (e) => {
                    self.submitBooking();
                });
            }
        } else {
            ra.bookings.addTextTag(tag, "h3", "Sorry, booking has now closed");
        }
        var displayWaitingList = false;
        if (maxPlaces === 0) {
            // fully booked
            ra.bookings.addTextTag(tag, "p", "This event is FULLY booked, ");
            if (this.bookingItem === null) {
                displayWaitingList = true;
            }
        }
        var waitingItem = this.evb.getWaiting(this.bookingData.md5Email);
        if (waitingItem !== null) {
            displayWaitingList = true;
        }
        if (displayWaitingList && maxPlaces !==0) {
            this.displayWaitingListOptions(tag);
        }
    }
    ;
    this.submitBooking = function () {
        const data = {
            ewid: this.ewid,
            ics: this.ics,
            user: this.user,
            bookingData: this.bookingData
        };
        var action = 'SubmitBooking';
        if (this.bookingData.attendees === 0) {
            action = 'CancelBooking';
        } else {
            if (this.evb.options.attendeetype === 'memonly') {
                if (this.bookingData.member !== 'Yes') {
                    ra.showMsg('This event is only open to Ramblers Members');
                    return;
                }
            }
            if (this.evb.options.attendeetype === 'undefined') {
                ra.showMsg('You have not specified if you are an Rambler Member or not');
                return;
            }
        }
        var sa = new ra.bookings.queryServer(this, action);
        sa.action(data, (self, results) => {
            self.refreshDisplay();
        });
    };
    this.displayWaitingListOptions = function (tag) {
        if (!this.evb.options.waitinglist) {
            return;
        }
        var waitingItem = this.evb.getWaiting(this.bookingData.md5Email);
        if (waitingItem === null) {
            ra.bookings.addTextTag(tag, "p", "If you wish to be notified when a place becomes available, please use the <b>Notify Me</b> option below.");
            var submit = this.input.addButton(tag, ['link-button', 'tiny', 'button', 'mintcake'], 'Notify Me');
            var self = this;
            submit.addEventListener('click', (e) => {
                self.Waiting();
            });
        } else {
            ra.bookings.addTextTag(tag, "p", "You are on our list to be notified when places become available.");
            var submit = this.input.addButton(tag, ['link-button', 'tiny', 'button', 'mintcake'], 'Remove Notify Me');
            var self = this;
            submit.addEventListener('click', (e) => {
                self.Waiting();
            });
        }
    };


    this.Waiting = function () {
        const data = {
            ewid: this.ewid,
            user: this.user,
            bookingData: this.bookingData
        };
//       
        var sa = new ra.bookings.queryServer(this, 'Waiting');
        sa.action(data, (self, results) => {
            self.refreshDisplay();
        });
    };

    this.changePaid = function (md5Email, amount) {
        const data = {
            md5Email: md5Email,
            ewid: this.ewid,
            paid: amount
        };
//      
        var sa = new ra.bookings.queryServer(this, 'AdminChangePaid');
        sa.action(data, (self, results) => {
            self.refreshDisplay();
        });
    };
    this.deleteBooker = function (raData) {
        const data = {
            md5Email: raData.user.md5Email,
            ewid: this.ewid
        };
//       
        var sa = new ra.bookings.queryServer(this, 'AdminDeleteSingleBooking');
        sa.action(data, (self, results) => {
            self.refreshDisplay();
        });
    };
    this.deleteWaiting = function (raData) {
        const data = {
            md5Email: raData.user.md5Email,
            ewid: this.ewid
        };
//     
        var sa = new ra.bookings.queryServer(this, 'AdminDeleteSingleWaiting');
        sa.action(data, (self, results) => {
            self.refreshDisplay();
        });
    };
    this.emailForm = function (options) {
        var div = document.createElement("div");
        div.classList.add('email');
        div.classList.add('booking');
        div.style.display = "inline-block";
        var emailModal = ra.modals.createModal(div, false);
        this.input.addComment(div, 'title', 'Event', options.subject);
        this.input.addComment(div, 'to', 'You are emailing', options.toWhom);
        this.input.addHtmlArea(div, 'desc', "Content:", 10, options, 'emailContent', 'Add a description of walk so walkers know what to expect', null);
        div.appendChild(document.createElement("p"));
        var button = this.input.addButton(div, ['submit', 'link-button', 'tiny', 'button', 'mintcake'], 'Send Email');
        button.addEventListener('click', (e) => {
            if (options.emailContent.length < 10) {
                ra.showMsg('Insufficient content to your email message');
            } else {
                button.style.display = 'none';
//            
                var sa = new ra.bookings.queryServer(this, options.serverAction);
                sa.action(options, (self, results) => {
                    emailModal.close();
                });
            }
        });
    };
};